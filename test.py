import pandas_market_calendars as mcal

# # See available calendars
# print(mcal.get_calendar_names())

# Get a specific calendar
nyse = mcal.get_calendar('NYSE')

# Get schedule for a date range
schedule = nyse.schedule(start_date='2026-07-01', end_date='2026-07-31')

print(schedule)
