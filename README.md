You will need to export the token api key for SmartOLT<br>
<br>
<br>
export SMARTOLT_TOKEN="YOUR_SMART_OLT_API_KEY"<br>
export SMARTOLT_SUB_DOMAIN="YOUR_SMARTOLT_DOMAIN"<br>
export NTFY_SERVER_URL="YOUR_NTFY_SERVER"<br>
export TWILLO_SERVER="http://YourCustomServer/api/twillo/call"<br>
<br>
<br>
Then you can run the program and it will send the notification to ntfy server,<br>
once per hour and uses the file last_notification_time.txt to keep track of<br>
the last notifcation.<br>

Udpated this to use docker now to schedule the cron jobs, you just
need to create a .env file and put exported variables in their
instead.
