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

SYMBOL_RE = re.compile(r'^[A-Z0-9\.\-]{1,12}$')  # allow letters, numbers, dot, dash

load_dotenv("/home/dh_92f9in/config/db.env")


# -------------------------
# DB connection
# -------------------------

conn = pymysql.connect(
    host=os.getenv("DB_HOST"),
    user=os.getenv("DB_USER"),
    password=os.getenv("DB_PASS"),
    database=os.getenv("DB_NAME"),
    charset="utf8mb4",
    cursorclass=pymysql.cursors.DictCursor
)


# -------------------------
# Get symbols missing from tickers table
# to update tickers table
# -------------------------


def get_missing_symbols(conn):
    sql = """
        SELECT DISTINCT wi.symbol
        FROM watchlist_items wi
        LEFT JOIN tickers t
          ON t.symbol = wi.symbol
        WHERE t.symbol IS NULL
    """
    with conn.cursor() as cur:
        cur.execute(sql)
        return [row["symbol"] for row in cur.fetchall()]
    

# -------------------------
# Fetch ticker info from yfinance
# -------------------------

def fetch_ticker_info(symbol):
    try:
        t = yf.Ticker(symbol)
        info = t.info

        name = (
            info.get("longName")
            or info.get("shortName")
            or symbol
        )

        ticker_type = "ETF" if info.get("quoteType") == "ETF" else "STOCK"

        # Use sector if available, otherwise fall back to type
        sector = info.get("sector") or ticker_type
        industry = info.get("industry") or ticker_type

        return {
            "symbol": symbol,
            "name": name,
            "type": ticker_type,
            "exchange": info.get("exchange"),
            "sector": info.get("sector"),
            "industry": info.get("industry"),
            "currency": info.get("currency")
        }

    except Exception as e:
        print(f"[WARN] {symbol}: failed to fetch info ({e})")
        return None


# -------------------------
# Update tickers table with new values
# -------------------------
def upsert_ticker(conn, data):
    sql = """
        INSERT INTO tickers
            (symbol, name, type, exchange, sector, industry, currency)
        VALUES
            (%(symbol)s, %(name)s, %(type)s, %(exchange)s,
             %(sector)s, %(industry)s, %(currency)s)
        ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            type = VALUES(type),
            exchange = VALUES(exchange),
            sector = VALUES(sector),
            industry = VALUES(industry),
            currency = VALUES(currency)
    """
    with conn.cursor() as cur:
        cur.execute(sql, data)

def main():
    symbols = get_missing_symbols(conn)
    print(f"Found {len(symbols)} symbols to enrich")

    for symbol in symbols:
        print(f"Fetching {symbol}...")
        data = fetch_ticker_info(symbol)

        if data:
            upsert_ticker(conn, data)
            conn.commit()

        time.sleep(0.5)  # be polite to Yahoo

    print("Ticker enrichment complete")


if __name__ == "__main__":
    main()
