import pymysql
from datetime import date
import os
from dotenv import load_dotenv
import re
import time

os.environ["OMP_NUM_THREADS"] = "1"
os.environ["OPENBLAS_NUM_THREADS"] = "1"
os.environ["MKL_NUM_THREADS"] = "1"
os.environ["VECLIB_MAXIMUM_THREADS"] = "1"
os.environ["NUMEXPR_NUM_THREADS"] = "1"

import yfinance as yf
import pandas as pd


SYMBOL_RE = re.compile(r'^[A-Z0-9\.\-]{1,12}$')  # allow letters, numbers, dot, dash

load_dotenv("/home/dh_92f9in/config/db.env")


Z_WINDOW = 20

# -------------------------
# DB HELPERS
# -------------------------

def get_db_connection():
    return pymysql.connect(
        host=os.getenv("DB_HOST"),
        user=os.getenv("DB_USER"),
        password=os.getenv("DB_PASS"),
        database=os.getenv("DB_NAME"),
        charset="utf8mb4",
        cursorclass=pymysql.cursors.DictCursor
    )




def fetch_active_symbols(conn):
    """
    Return a cleaned list of uppercase symbols pulled from the DB.
    Also prints (for debugging) the raw rows returned.
    """
    query = """
        SELECT DISTINCT wi.symbol AS symbol
        FROM watchlist_items wi
        JOIN watchlists w ON wi.watch_list_id = w.watch_list_id
        WHERE w.active = 1
        -- and wi.symbol = 'FSLR'   -- you can temporarily enable this for one-off testing
    """

    cursor = conn.cursor()
    cursor.execute(query)
    rows = cursor.fetchall()   # with DictCursor each row is a dict

    print("DEBUG raw rows from DB:", rows)

    cleaned = []
    for r in rows:
        # r might be {'symbol': 'AAPL'} or {'symbol': None} etc.
        raw = r.get('symbol') if isinstance(r, dict) else r[0]
        if raw is None:
            print("Skipping NULL symbol row:", r)
            continue

        s = str(raw).strip().upper()

        # Normalize common Yahoo / ticker formatting (optional)
        # e.g. convert 'BRK.B' -> 'BRK-B' for yfinance/Yahoo if you want:
        # s = s.replace('.', '-')

        if not s:
            print("Skipping empty symbol after strip:", repr(raw))
            continue

        # Quick validation
        if not SYMBOL_RE.match(s):
            print("Skipping invalid symbol (fails regex):", repr(s))
            continue

        cleaned.append(s)

    # final debug
    print("DEBUG cleaned symbols list:", cleaned)
    return cleaned



def upsert_signal(conn, signal):
    sql = """
        INSERT INTO signals (symbol, indicator, value, as_of_date)
        VALUES (%s, %s, %s, %s)
        ON DUPLICATE KEY UPDATE
            value = VALUES(value),
            created_at = CURRENT_TIMESTAMP
    """
    cursor = conn.cursor()
    cursor.execute(
        sql,
        (
            signal["symbol"],
            signal["indicator"],
            signal["value"],
            signal["as_of_date"],
        )
    )
    conn.commit()
    cursor.close()

# -------------------------
# Z-SCORE LOGIC
# -------------------------

def calculate_zscore(symbol, window=20):
    df = yf.download(
        symbol,
        period="6mo",
        interval="1d",
        auto_adjust=True,
        progress=False,
        threads=False,
    )

    if df.empty or len(df) < window:
        return None

    close = df["Close"]
    ma = close.rolling(window).mean()
    std = close.rolling(window).std()
    z = (close - ma) / std

    z = z.dropna()
    if z.empty:
        return None

    return {
        "symbol": symbol,
        "indicator": f"z_score_{window}",
        "value": float(z.iloc[-1]),
        "as_of_date": z.index[-1].date(),
    }

# -------------------------
# MAIN JOB
# -------------------------

def main():
    conn = get_db_connection()

    try:
        symbols = fetch_active_symbols(conn)
        print("RAW symbols list:", symbols)
        print(f"Found {len(symbols)} active symbols")


        for symbol in symbols:
            print("DEBUG symbol value:", symbol)
            print("DEBUG symbol type:", type(symbol))
            try:
                signal = calculate_zscore(symbol, Z_WINDOW)
                if signal:
                    upsert_signal(conn, signal)
                    print(f"Updated {symbol}: {signal['value']:.2f}")
                else:
                    print(f"Skipped {symbol} (insufficient data)")
            except Exception as e:
                print(f"Error processing {symbol}: {e}")
            time.sleep(0.5)

    finally:
        conn.close()


if __name__ == "__main__":
    main()
