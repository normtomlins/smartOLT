You will need to export the token api key for SmartOLT


export SMARTOLT_TOKEN="YOUR_SMART_OLT_API_KEY"
export SMARTOLT_SUB_DOMAIN="YOUR_SMARTOLT_DOMAIN"
export NTFY_SERVER_URL="YOUR_NTFY_SERVER"
export TWILLO_SERVER="http://YourCustomServer/api/twillo/call"


Then you can run the program and it will send the notification to ntfy server,
once per hour and uses the file last_notification_time.txt to keep track of
the last notifcation.
