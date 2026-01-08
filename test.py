# import yfinance as yf
# yf.download("AAPL", period="1mo")


import finnhub
finnhub_client = finnhub.Client(api_key="d5d9o9hr01qur4iqrsh0d5d9o9hr01qur4iqrshg")

print(finnhub_client.company_news('AMAT', _from="2026-01-01", to="2026-01-07"))
