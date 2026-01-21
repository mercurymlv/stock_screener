import pymysql
from datetime import date
import os
from dotenv import load_dotenv
import time
import yfinance as yf
import re


# NETWORKING FIX: Force synchronous DNS for Cron sessions
os.environ["CURL_OPT_NOSIGNAL"] = "1"

# Standard environment variables for stable cron execution
os.environ["OMP_NUM_THREADS"] = "1"
os.environ["OPENBLAS_NUM_THREADS"] = "1"
os.environ["MKL_NUM_THREADS"] = "1"
os.environ["VECLIB_MAXIMUM_THREADS"] = "1"
os.environ["NUMEXPR_NUM_THREADS"] = "1"

load_dotenv("/home/dh_92f9in/config/db.env")

# Regex to ensure we only process valid ticker symbols
SYMBOL_RE = re.compile(r'^[A-Z0-9\.\-]{1,12}$')

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
    query = """
        SELECT DISTINCT wi.symbol AS symbol
        FROM watchlist_items wi
        JOIN watchlists w ON wi.watch_list_id = w.watch_list_id
        WHERE w.active = 1
    """
    cursor = conn.cursor()
    cursor.execute(query)
    rows = cursor.fetchall()
    
    cleaned = []
    for r in rows:
        raw = r.get('symbol')
        if raw is None: continue
        
        s = str(raw).strip().upper()
        if s and SYMBOL_RE.match(s):
            cleaned.append(s)
            
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
    cursor.execute(sql, (
        signal["symbol"],
        signal["indicator"],
        signal["value"],
        signal["as_of_date"],
    ))
    conn.commit()
    cursor.close()

# -------------------------
# SCRAPER LOGIC
# -------------------------

def get_fundamental_signals(ticker_obj, symbol):
    """Safe extraction of info dictionary keys."""
    metrics_to_track = {
        'trailingPE': 'pe_ratio',
        'forwardPE': 'forward_pe',
        'trailingPegRatio': 'peg_ratio',
        'dividendYield': 'div_yield',
        'debtToEquity': 'debt_equity',
        'currentRatio': 'current_ratio',
        'returnOnEquity': 'roe',
        'revenueGrowth': 'rev_growth'
    }
    
    info = ticker_obj.info
    found_signals = []
    
    if not info:
        return []

    for yf_key, db_indicator in metrics_to_track.items():
        val = info.get(yf_key)
        
        if val is not None and isinstance(val, (int, float)):
            # Normalize yields/growth (0.05 -> 5.0) but leave ratios (PE/Debt) alone
            if any(x in yf_key.lower() for x in ['yield', 'growth']):
                if -1.0 < val < 1.0: 
                    val = val * 100
            
            found_signals.append({
                "symbol": symbol,
                "indicator": db_indicator,
                "value": float(val),
                "as_of_date": date.today()
            })
            
    return found_signals

# -------------------------
# MAIN JOB
# -------------------------

def main():
    conn = get_db_connection()
    try:
        symbols = fetch_active_symbols(conn)
        print(f"Starting fundamental update for {len(symbols)} symbols...")

        for symbol in symbols:
            try:
                print(f"--- Processing {symbol} ---")
                ticker = yf.Ticker(symbol)
                
                signals = get_fundamental_signals(ticker, symbol)
                for sig in signals:
                    upsert_signal(conn, sig)
                    print(f"  [DB] {sig['indicator']}: {sig['value']:.2f}")
                
                print(f"✅ Finished {symbol}")
                
                # Rate limit protection for shared hosting
                time.sleep(1.5)

            except Exception as e:
                print(f"❌ Error on {symbol}: {e}")
                time.sleep(5) 

    finally:
        conn.close()
        print("Job complete. Database connection closed.")

if __name__ == "__main__":
    main()