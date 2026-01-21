import finnhub
from datetime import datetime

# Initialize client
# Using your provided key
finnhub_client = finnhub.Client(api_key="d5d9o9hr01qur4iqrsh0d5d9o9hr01qur4iqrshg")

def get_sbux_news():
    symbol = 'SBUX'
    
    # Let's get news for the last 7 days
    # (Note: For a production script, you'd calculate these dates dynamically)
    from_date = "2026-01-13" 
    to_date = "2026-01-20"

    print(f"--- Fetching news for {symbol} from {from_date} to {to_date} ---")
    
    try:
        news_items = finnhub_client.company_news(symbol, _from=from_date, to=to_date)
        
        if not news_items:
            print("No news found for this period.")
            return

        # Limit to the top 10 most recent for the preview
        for item in news_items[:10]:
            # Convert UNIX timestamp to readable format
            dt_object = datetime.fromtimestamp(item['datetime'])
            date_str = dt_object.strftime('%Y-%m-%d %H:%M')

            print(f"\n[{date_str}] | SOURCE: {item['source']}")
            print(f"HEADLINE: {item['headline']}")
            print(f"SUMMARY: {item['summary'][:150]}...") # Truncate long summaries
            print(f"URL: {item['url']}")
            print("-" * 50)

    except Exception as e:
        print(f"Error fetching news: {e}")

if __name__ == "__main__":
    get_sbux_news()