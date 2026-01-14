import yfinance as yf
import pandas as pd
import pymysql
from datetime import date


# Signal registry - can scale and add more signals here

SIGNALS = [
    {
        "name": "z_score_20",
        "func": lambda close: z_score_signal(close, window=20),
    },
    {
        "name": "rsi_14",
        "func": lambda close: rsi_signal(close, period=14),
    },
]


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
    """
    df = pd.read_sql(query, conn)
    return df["symbol"].tolist()

# fetch the price history - used for multiple signals
def fetch_price_data(symbol):
    return yf.download(
        symbol,
        period="6mo",
        interval="1d",
        auto_adjust=True,
        progress=False,
    )

# z-score calculation uses closing prices
def z_score_signal(close, window=20):
    ma = close.rolling(window).mean()
    std = close.rolling(window).std()
    z = (close - ma) / std
    z = z.dropna()
    return float(z.iloc[-1]) if not z.empty else None


# Relative Strength Index (RSI) calculation
def rsi_signal(close, period=14):
    delta = close.diff()
    gain = delta.clip(lower=0)
    loss = -delta.clip(upper=0)

    avg_gain = gain.rolling(period).mean()
    avg_loss = loss.rolling(period).mean()

    rs = avg_gain / avg_loss
    rsi = 100 - (100 / (1 + rs))
    rsi = rsi.dropna()
    return float(rsi.iloc[-1]) if not rsi.empty else None



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


# compute_signals_for_symbol

def compute_signals_for_symbol(symbol):
    df = fetch_price_data(symbol)
    if df.empty:
        return []

    close = df["Close"]
    as_of_date = df.index[-1].date()

    results = []

    for signal in SIGNALS:
        value = signal["func"](close)
        if value is not None:
            results.append({
                "symbol": symbol,
                "indicator": signal["name"],
                "value": value,
                "as_of_date": as_of_date,
            })

    return results

def main():
    conn = get_db_connection()

    try:
        symbols = fetch_active_symbols(conn)
        print(f"Found {len(symbols)} active symbols")

        for symbol in symbols:
            try:
                signals = compute_signals_for_symbol(symbol)

                if not signals:
                    print(f"Skipped {symbol} (no signals)")
                    continue

                for signal in signals:
                    upsert_signal(conn, signal)
                    print(
                        f"Updated {symbol} "
                        f"{signal['indicator']}: {signal['value']:.2f}"
                    )

            except Exception as e:
                print(f"Error processing {symbol}: {e}")

    finally:
        conn.close()


if __name__ == "__main__":  
    main()
