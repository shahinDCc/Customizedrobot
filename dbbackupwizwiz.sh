#!/bin/bash

# Configuration
BASE_INFO_FILE="/var/www/html/wizwizxui-timebot/baseInfo.php"
BACKUP_DIR="/tmp/db_backup"
LOG_FILE="/var/log/db_backup.log"
BACKUP_FILENAME="wizwiz_$(date +'%Y-%m-%d_%H-%M-%S').sql"

# Functions
log_message() {
    echo "$(date +'%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"
}

exit_with_error() {
    log_message "ERROR: $1"
    exit 1
}

check_dependency() {
    local cmd=$1
    if ! command -v "$cmd" &> /dev/null; then
        exit_with_error "$cmd is not installed. Please install it and try again."
    fi
}

extract_value() {
    local key=$1
    grep "$key" "$BASE_INFO_FILE" | cut -d"'" -f2
}

# Dependency Check
check_dependency "mysqldump"
check_dependency "curl"

# Check if configuration file exists
if [[ ! -f "$BASE_INFO_FILE" ]]; then
    exit_with_error "Configuration file not found at $BASE_INFO_FILE."
fi

# Extract credentials and information
telegramBotToken=$(extract_value '\$botToken')
chatID=$(grep '\$admin =' "$BASE_INFO_FILE" | sed -E 's/.*= ([0-9]+);/\1/')
databaseUser=$(extract_value '\$dbUserName')
databasePassword=$(extract_value '\$dbPassword')
databaseName=$(extract_value '\$dbName')

# Validate extracted values
for var in telegramBotToken chatID databaseUser databasePassword databaseName; do
    if [[ -z "${!var}" ]]; then
        exit_with_error "$var is missing or empty in the configuration file."
    fi
done

# Create backup directory
if ! mkdir -p "$BACKUP_DIR"; then
    exit_with_error "Failed to create backup directory at $BACKUP_DIR."
fi

# Backup the database
BACKUP_PATH="$BACKUP_DIR/$BACKUP_FILENAME"
log_message "Starting database backup..."
if ! mysqldump -u"$databaseUser" -p"$databasePassword" "$databaseName" > "$BACKUP_PATH"; then
    rm -f "$BACKUP_PATH"
    exit_with_error "Database backup failed."
fi
log_message "Database backup completed successfully."

# Verify backup file
if [[ ! -s "$BACKUP_PATH" ]]; then
    rm -f "$BACKUP_PATH"
    exit_with_error "Backup file is empty or not created."
fi

# Send the backup file to Telegram
log_message "Uploading backup file to Telegram..."
TELEGRAM_API="https://api.telegram.org/bot$telegramBotToken/sendDocument"
if ! curl -s -F "chat_id=$chatID" -F "document=@$BACKUP_PATH" "$TELEGRAM_API"; then
    rm -f "$BACKUP_PATH"
    exit_with_error "Failed to upload the backup to Telegram."
fi
log_message "Backup file uploaded successfully."

# Clean up backup file
rm -f "$BACKUP_PATH"
log_message "Temporary backup file removed."

log_message "Backup process completed successfully."
exit 0

