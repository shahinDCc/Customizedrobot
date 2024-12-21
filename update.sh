#!/bin/bash

# Written By: wizwiz
# Improved By: YourName

# Function to check if script is run as root
check_root() {
    if [ "$(id -u)" -ne 0 ]; then
        echo -e "\033[33mError: This script must be run as root.\033[0m"
        exit 1
    fi
}

# Function to install required packages
install_packages() {
    local packages=(git wget unzip curl)
    for package in "${packages[@]}"; do
        if ! dpkg -l | grep -q "^ii  $package "; then
            sudo apt-get install -y "$package"
            if [ $? -ne 0 ]; then
                echo -e "\e[41mError: Failed to install $package. Please check your internet connection and package manager.\033[0m"
                exit 1
            fi
        fi
    done
}

# Function to send a message via Telegram bot
send_telegram_message() {
    local bot_token=$1
    local chat_id=$2
    local message=$3

    response=$(curl -s -w "%{http_code}" -o /dev/null -X POST "https://api.telegram.org/bot${bot_token}/sendMessage" \
        -d chat_id="${chat_id}" \
        -d text="${message}" \
        -d parse_mode="html")

    if [ "$response" -ne 200 ]; then
        echo -e "\e[41mError: Failed to send Telegram message. HTTP response code: $response.\033[0m"
    fi
}

# Function to securely update the bot
update_bot() {
    echo " "
    read -p "Are you sure you want to update? [y/n]: " answer
    echo " "
    if [[ "$answer" =~ ^[Yy]$ ]]; then
        local base_info_path="/var/www/html/wizwizxui-timebot/baseInfo.php"

        if [ ! -f "$base_info_path" ]; then
            echo -e "\e[41mError: baseInfo.php not found at $base_info_path. Update aborted.\033[0m"
            exit 1
        fi

        mv "$base_info_path" /root/

        install_packages

        echo -e "\n\e[92mUpdating...\033[0m\n"
        sleep 2

        if ! rm -rf /var/www/html/wizwizxui-timebot/; then
            echo -e "\e[41mError: Failed to remove old bot directory. Check permissions.\033[0m"
            exit 1
        fi

        if ! git clone https://github.com/wizwizdev/wizwizxui-timebot.git /var/www/html/wizwizxui-timebot; then
            echo -e "\e[41mError: Failed to clone repository. Check your internet connection.\033[0m"
            exit 1
        fi

        sudo chown -R www-data:www-data /var/www/html/wizwizxui-timebot/
        sudo chmod -R 755 /var/www/html/wizwizxui-timebot/

        if [ -f /root/baseInfo.php ]; then
            mv /root/baseInfo.php "$base_info_path"
        else
            echo -e "\e[41mError: baseInfo.php backup not found. Update aborted.\033[0m"
            exit 1
        fi

        # Extracting details from baseInfo.php
        local db_name db_user db_pass bot_token bot_admin
        db_name=$(grep '\$dbName' "$base_info_path" | cut -d"'" -f2)
        db_user=$(grep '\$dbUserName' "$base_info_path" | cut -d"'" -f2)
        db_pass=$(grep '\$dbPassword' "$base_info_path" | cut -d"'" -f2)
        bot_token=$(grep '\$botToken' "$base_info_path" | cut -d"'" -f2)
        bot_admin=$(grep '\$admin =' "$base_info_path" | sed 's/.*= //' | sed 's/;//')

        if [ -z "$bot_token" ] || [ -z "$bot_admin" ]; then
            echo -e "\e[41mError: Missing bot token or admin ID in baseInfo.php. Update aborted.\033[0m"
            exit 1
        fi

        local message="🤖 WizWiz robot has been successfully updated!\n\n"
        message+="🔻Token: <code>${bot_token}</code>\n"
        message+="🔻Admin: <code>${bot_admin}</code>\n"
        message+="🔹DB Name: <code>${db_name}</code>\n"
        message+="🔹DB User: <code>${db_user}</code>\n"
        message+="🔹DB Pass: <code>${db_pass}</code>"

        send_telegram_message "$bot_token" "$bot_admin" "$message"

        echo -e "\n\e[92mThe script was successfully updated!\033[0m\n"
    else
        echo -e "\e[41mUpdate canceled.\033[0m\n"
    fi
}

# Display menu options
display_menu() {
    PS3=" Please Select Action: "
    options=("Update bot" "Exit")
    select opt in "${options[@]}"; do
        case $opt in
            "Update bot")
                update_bot
                break
                ;;
            "Exit")
                echo "Exiting..."
                break
                ;;
            *)
                echo "Invalid option! Please select a valid action."
                ;;
        esac
    done
}

# Main execution
check_root
display_menu
