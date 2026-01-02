import yfinance as yf
import pandas as pd
import pymysql
from datetime import date


Z_WINDOW = 20

# -------------------------
# DB HELPERS
# -------------------------

def get_db_connection():
    return pymysql.connect(
        host="localhost",
        user="mavaldez",
        password="Gogo125!",
        database="stocks_dev",
        charset="utf8mb4",
        cursorclass=pymysql.cursors.DictCursor
    )


def fetch_active_symbols(conn):
    query = """
        SELECT DISTINCT wi.symbol
        FROM watchlist_items wi
        JOIN watchlists w on wi.watch_list_id=w.watch_list_id
        WHERE w.active = 1
        and wi.symbol = 'ADBE'
    """
    df = pd.read_sql(query, conn)
    return df["symbol"].tolist()


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
        print(f"Found {len(symbols)} active symbols")

        for symbol in symbols:
            try:
                signal = calculate_zscore(symbol, Z_WINDOW)
                if signal:
                    upsert_signal(conn, signal)
                    print(f"Updated {symbol}: {signal['value']:.2f}")
                else:
                    print(f"Skipped {symbol} (insufficient data)")
            except Exception as e:
                print(f"Error processing {symbol}: {e}")

    finally:
        conn.close()


if __name__ == "__main__":
    main()
