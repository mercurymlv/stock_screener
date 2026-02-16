import os

# -------------------------
# ENV / PERFORMANCE LIMITS
# -------------------------
os.environ["OMP_NUM_THREADS"] = "1"
os.environ["OPENBLAS_NUM_THREADS"] = "1"
os.environ["MKL_NUM_THREADS"] = "1"
os.environ["VECLIB_MAXIMUM_THREADS"] = "1"
os.environ["NUMEXPR_NUM_THREADS"] = "1"


import time
import logging
from datetime import datetime
import pymysql
import pandas as pd
from dotenv import load_dotenv

load_dotenv("/home/dh_92f9in/config/db.env")

# -------------------------
# CONFIG
# -------------------------
Z_WINDOW = 20
DELTA_Z_DAYS = 3
Z_INDICATOR = f"z_score_{Z_WINDOW}"

# -------------------------
# LOGGING
# -------------------------
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
)
logger = logging.getLogger(__name__)

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
        cursorclass=pymysql.cursors.DictCursor,
        autocommit=True,
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



def fetch_recent_signals(conn, symbol, indicator, limit):
    """
    Fetch the most recent `limit` z-score values for a symbol and indicator.
    Returns list of dicts with 'value' and 'as_of_date', oldest first.
    """
    sql = """
        SELECT value, as_of_date
        FROM signals
        WHERE symbol = %s
          AND indicator = %s
        ORDER BY as_of_date DESC
        LIMIT %s
    """
    with conn.cursor() as cursor:
        cursor.execute(sql, (symbol, indicator, limit))
        rows = cursor.fetchall()
    return list(reversed(rows))  # oldest first

def upsert_signal(conn, signal):
    """
    Insert or update a signal record.
    """
    sql = """
        INSERT INTO signals (symbol, indicator, value, as_of_date)
        VALUES (%s, %s, %s, %s)
        ON DUPLICATE KEY UPDATE
            value = VALUES(value),
            created_at = CURRENT_TIMESTAMP
    """
    with conn.cursor() as cursor:
        cursor.execute(
            sql,
            (
                signal["symbol"],
                signal["indicator"],
                signal["value"],
                signal["as_of_date"],
            )
        )

# -------------------------
# SIGNAL CALCULATORS
# -------------------------
def calculate_delta_z(values, days=3):
    """
    Delta-z over `days` horizon.
    """
    if len(values) <= days:
        return None
    return values[-1] - values[-days-1]

def calculate_z_of_z(values, window=20):
    """
    Z-of-z (meta-normalization) over `window`.
    """
    if len(values) < window:
        return None
    series = pd.Series(values[-window:])
    z_of_z = (series - series.mean()) / series.std()
    return float(z_of_z.iloc[-1])

def process_symbol(conn, symbol):
    """
    Fetch recent history and calculate delta-z and z-of-z.
    """
    limit = max(DELTA_Z_DAYS + 1, Z_WINDOW + 5)
    rows = fetch_recent_signals(conn, symbol, Z_INDICATOR, limit)
    
    if not rows:
        return None, None
    
    values = [r['value'] for r in rows]
    delta_z = calculate_delta_z(values, days=DELTA_Z_DAYS)
    z_of_z = calculate_z_of_z(values, window=Z_WINDOW)
    
    return delta_z, z_of_z

# -------------------------
# MAIN
# -------------------------
def main():
    logger.info("Composite signals job starting")
    conn = get_db_connection()

    # example symbol list; replace with dynamic fetch if needed
    symbols = fetch_active_symbols(conn)

    updated = 0
    skipped = 0

    try:
        for symbol in symbols:
            try:
                delta_z, z_of_z = process_symbol(conn, symbol)
                
                if delta_z is None and z_of_z is None:
                    logger.info("%s: insufficient data, skipping", symbol)
                    skipped += 1
                    continue

                # upsert delta-z
                if delta_z is not None:
                    upsert_signal(conn, {
                        "symbol": symbol,
                        "indicator": f"{Z_INDICATOR}_delta_{DELTA_Z_DAYS}d",
                        "value": delta_z,
                        "as_of_date": datetime.utcnow().date()
                    })

                # upsert z-of-z
                if z_of_z is not None:
                    upsert_signal(conn, {
                        "symbol": symbol,
                        "indicator": f"{Z_INDICATOR}_z_of_z",
                        "value": z_of_z,
                        "as_of_date": datetime.utcnow().date()
                    })

                logger.info("%s: delta_z=%.3f z_of_z=%.3f", symbol,
                            delta_z if delta_z is not None else float('nan'),
                            z_of_z if z_of_z is not None else float('nan'))
                updated += 1

            except Exception as e:
                logger.exception("%s: error processing symbol", symbol)

            time.sleep(0.1)  # throttle

    finally:
        conn.close()

    logger.info("Job complete | updated=%d skipped=%d", updated, skipped)

if __name__ == "__main__":
    main()
