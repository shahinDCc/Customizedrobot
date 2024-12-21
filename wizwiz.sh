#!/bin/bash

# Author: wizwiz
# Fully enhanced and modular version of the wizwiz.sh script

set -euo pipefail # Enhanced error handling

log_file="/var/log/wizwiz_install.log"
echo "Installation started at $(date)" > $log_file

# Function to log messages
log_message() {
    echo "$1" | tee -a $log_file
}

# Function to check if the script is run as root
check_root() {
    if [ "$(id -u)" -ne 0 ]; then
        log_message "\033[33mPlease run the script as root\033[0m"
        exit 1
    fi
}

# Function to install required packages
install_packages() {
    local packages=(
        lamp-server^ libapache2-mod-php mysql-server apache2 php-mbstring \
        php-zip php-gd php-json php-curl phpmyadmin php-soap git wget unzip curl php-ssh2
    )

    log_message "\e[32mInstalling required packages...\033[0m"
    apt update && apt upgrade -y

    for pkg in "${packages[@]}"; do
        if dpkg -s "$pkg" &> /dev/null; then
            log_message "$pkg is already installed"
        else
            apt install "$pkg" -y
            log_message "$pkg successfully installed"
        fi
    done
}

# Function to configure phpMyAdmin
configure_phpmyadmin() {
    random_password=$(openssl rand -base64 10 | tr -dc 'a-zA-Z0-9' | cut -c1-20)

    echo "phpmyadmin phpmyadmin/dbconfig-install boolean true" | debconf-set-selections
    echo "phpmyadmin phpmyadmin/app-password-confirm password $random_password" | debconf-set-selections
    echo "phpmyadmin phpmyadmin/mysql/admin-pass password $random_password" | debconf-set-selections
    echo "phpmyadmin phpmyadmin/mysql/app-pass password $random_password" | debconf-set-selections
    echo "phpmyadmin phpmyadmin/reconfigure-webserver multiselect apache2" | debconf-set-selections

    apt install phpmyadmin -y
    ln -sf /etc/phpmyadmin/apache.conf /etc/apache2/conf-available/phpmyadmin.conf
    a2enconf phpmyadmin.conf
    systemctl restart apache2

    log_message "phpMyAdmin configured successfully"
}

# Function to configure the firewall
configure_firewall() {
    log_message "\e[32mConfiguring firewall...\033[0m"
    ufw allow 'Apache'
    ufw allow 80
    ufw allow 443
    log_message "Firewall rules configured"
}

# Function to set up an SSL certificate
setup_ssl_certificate() {
    read -p "Enter your domain name: " domainname

    if [[ ! "$domainname" =~ ^[a-zA-Z0-9.-]+$ ]]; then
        log_message "\033[91mInvalid domain name. Please try again.\033[0m"
        exit 1
    fi

    log_message "\e[32mInstalling SSL certificate...\033[0m"
    apt install letsencrypt python3-certbot-apache -y
    certbot --apache --agree-tos --preferred-challenges http -d "$domainname"

    log_message "SSL certificate successfully issued for $domainname"
}

# Function to set up the database
setup_database() {
    local dbname="wizwiz"
    local dbuser=$(openssl rand -base64 10 | tr -dc 'a-zA-Z0-9' | cut -c1-12)
    local dbpass=$(openssl rand -base64 10 | tr -dc 'a-zA-Z0-9' | cut -c1-20)

    log_message "\e[32mSetting up the database...\033[0m"
    mysql -u root -e "CREATE DATABASE $dbname;"
    mysql -u root -e "CREATE USER '$dbuser'@'localhost' IDENTIFIED BY '$dbpass';"
    mysql -u root -e "GRANT ALL PRIVILEGES ON $dbname.* TO '$dbuser'@'localhost';"
    mysql -u root -e "FLUSH PRIVILEGES;"

    log_message "Database $dbname successfully created"
    log_message "\e[100mDatabase Information:\033[0m"
    log_message "\e[33mAddress: \e[36mhttp://$domainname/phpmyadmin\033[0m"
    log_message "\e[33mDatabase name: \e[36m$dbname\033[0m"
    log_message "\e[33mUsername: \e[36m$dbuser\033[0m"
    log_message "\e[33mPassword: \e[36m$dbpass\033[0m"
}

# Function to clean up temporary files
cleanup_temp_files() {
    log_message "\e[32mCleaning up temporary files...\033[0m"
    temp_files=("/var/www/html/tempCookie.txt" "/var/www/html/README.md")

    for file in "${temp_files[@]}"; do
        if [ -f "$file" ]; then
            rm "$file"
            log_message "$file removed"
        fi
    done
}

# Main function to execute all steps
main() {
    check_root
    install_packages
    configure_phpmyadmin
    configure_firewall
    setup_ssl_certificate
    setup_database
    cleanup_temp_files

    log_message "\e[32mScript execution completed successfully. Logs are stored in $log_file.\033[0m"
}

main
