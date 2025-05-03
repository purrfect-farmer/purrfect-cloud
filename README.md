<p align="center"><a href="https://t.me/purrfect_community" target="_blank"><img src="resources/images/icon.png" width="192" alt="Purrfect Logo"></a></p>

<h1 align="center">Purrfect Cloud</h1>

### Requirements
- Telegram Bot Token
- Telegram Group with Topics
- Telegram Bot must be an admin of the group
- Required Topics (Announcements, Errors)
- Additional Topics for Each Farmer

### Setup
##### Install Packages
```bash
sudo apt-get update
sudo apt-get install software-properties-common -y
sudo LC_ALL=C.UTF-8 add-apt-repository -y ppa:ondrej/php
sudo apt-get update
sudo apt-get install php8.3 php8.3-dev php8.3-xml php8.3-zip php8.3-gmp php8.3-cli php8.3-mbstring php8.3-ffi php8.3-iconv php8.3-sqlite3 php8.3-curl php8.3-intl php8.3-mysql php-pear libuv1-dev nghttp2 composer micro -y
sudo pecl install uv-beta
echo extension=uv.so | sudo tee $(php --ini | sed '/additional .ini/!d;s/.*: //g')/uv.ini
echo ffi.enable=1 | sudo tee $(php --ini | sed '/additional .ini/!d;s/.*: //g')/ffi.ini

echo 262144 | sudo tee /proc/sys/vm/max_map_count
echo vm.max_map_count=262144 | sudo tee /etc/sysctl.d/40-madelineproto.conf

sudo mkdir -p /etc/security/limits.d/

echo '* soft nofile 1048576' | sudo tee -a /etc/security/limits.d/40-madelineproto.conf
echo '* hard nofile 1048576' | sudo tee -a /etc/security/limits.d/40-madelineproto.conf

cd /tmp
sudo apt-get install build-essential
git clone https://github.com/danog/PrimeModule-ext
cd PrimeModule-ext && make -j$(nproc) && sudo make install
```

##### Setup Node.js
```bash
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.2/install.sh | bash

\. "$HOME/.nvm/nvm.sh"

nvm install --lts

npm i -g npm
npm i -g pnpm
```

There are two methods of setup to follow, it's recommended to use the Nginx method:

[Proceed with Nginx Installation (Recommended)](#nginx-installation-recommended)

[Proceed with Regular Setup](#regular-setup)


### Nginx Installation (Recommended)

To setup Purrfect Cloud with Nginx, you will need to run the following commands.

##### Remove Apache
```bash
sudo apt remove apache2 apache2-utils apache2-bin -y
sudo apt autoremove -y
```

##### Install Nginx
```bash
sudo apt update && sudo apt install nginx php8.3-fpm php8.3-cli php8.3-mysql php8.3-curl php8.3-mbstring php8.3-xml php8.3-zip php8.3-bcmath -y
```

##### Add User to www-data group
```bash
sudo usermod -aG www-data $USER && newgrp www-data
```

##### Change Owner of /var/www
```bash
sudo chown -R www-data:www-data /var/www
```

##### Change Permission of /var/www
```bash
sudo chmod -R 775 /var/www
```

##### Change Working Directory to /var/www
```bash
cd /var/www
```

[Nginx - Proceed with Installation](#installation)


### Regular Setup

Use this method for setting up Purrfect Cloud in a different directory.

##### Change Working Directory to Home or Desired Path
```bash
cd ~
```

[Regular - Proceed with Installation](#installation)

### Installation

##### Clone the repository
```bash
git clone https://github.com/purrfect-farmer/purrfect-cloud.git
```

##### Change Working Directory to Purrfect Cloud
```bash
cd purrfect-cloud
```

##### Set Permisisons
```bash
sudo find storage -type d -exec chmod 775 {} \;                                               
sudo find storage -type f -exec chmod 664 {} \;
sudo find bootstrap/cache -type d -exec chmod 775 {} \;
sudo find bootstrap/cache -type f -exec chmod 664 {} \;
```

##### Install Composer Packages
```bash
composer i
```

##### Install Node.js Packages
```bash
pnpm install
```

##### Build Front-End
```bash
pnpm run build
```

##### Setup .env
```bash
cp .env.example .env
```

##### Generate Key
```bash
php artisan key:generate
```

##### Run migrations
```bash
php artisan migrate --seed --force
```

#### Set Database File Permission
```bash
chmod 664 database/database.sqlite
```


##### Extracting Group ID and Topic ID
Before proceeding, obtain your Telegram Group ID and Topic ID by sending a message to a topic in your group and copy the message link.

Then you can extract the Group ID and Topic ID from the link:
`https://t.me/c/{group_id}/{topic_id}/{message_id}`

An example: `https://t.me/c/2322054671/10837/34926`

The Group ID is always the same but the Topic ID changes.

##### Update .env

For `Micro`: press (`Ctrl+S` then `Ctrl+Q`) to save.

For `Nano`: press (`Ctrl+S` then `Ctrl+X`) to save.

```bash
micro .env
```

Note: When setting the `TELEGRAM_CHAT_ID`, it should always start with `-100` like `-100{group_id}` e.g `-1002322054671`.

Set the appropriate Topic ID for any key that ends with `_THREAD_ID` e.g `TELEGRAM_CHAT_ANNOUNCEMENT_THREAD_ID`

##### Important Entries
Entry | Description
--- | ---
`APP_NAME` | Server Name
`TELEGRAM_BOT_TOKEN` | Your Telegram Bot Token
`TELEGRAM_CHAT_ID` | Group ID e.g `-100{group_id}`
`TELEGRAM_CHAT_ANNOUNCEMENT_THREAD_ID` | Announcement Topic ID
`TELEGRAM_CHAT_ERROR_THREAD_ID` | Error Topic ID
`DISPLAY_FARMER_TITLE` | Displays Farmer Title


##### Additional Entries for Farmers
Entry | Description
--- | ---
`FARMER_EXAMPLE_ENABLED` | Farmer is Enabled
`FARMER_EXAMPLE_THREAD_ID` | Farmer Topic ID

Now continue the setup using your preferred method:

[Proceed with Nginx Installation (Recommended)](#nginx-server)

[Proceed with Regular Installation](#regular-installation---cron-job)


### Nginx Server

##### Change Group of /var/www/purrfect-cloud
```bash
sudo chown -R $USER:www-data /var/www/purrfect-cloud
```

##### Create Purrfect Cloud Nginx Server Block
```bash
sudo micro /etc/nginx/sites-available/purrfect-cloud
```

##### Add Block Code

Paste the following block and save.

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name _; # Change if needed
    root /var/www/purrfect-cloud/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

##### Disable Default Nginx Server
```bash
sudo rm /etc/nginx/sites-enabled/default
```

##### Enable Purrfect Cloud Server
```bash
sudo ln -s /etc/nginx/sites-available/purrfect-cloud /etc/nginx/sites-enabled/
```

##### Reload Nginx
```bash
sudo nginx -t
sudo systemctl reload nginx
```

### Nginx Installation - Cron Job

A cron job is required for scheduled tasks.

##### Register a Cron Job for Scheduled Tasks

Run the following command:

```bash
echo '* * * * * www-data cd /var/www/purrfect-cloud && php artisan schedule:run >> /dev/null 2>&1' | sudo tee -a /etc/crontab
```

[Nginx - Continue to Adding a User](#adding-a-user)

### Regular Installation - Cron Job

A cron job is required for scheduled tasks.

##### Edit Crontab
```bash
crontab -e
```


##### Register a Cron Job for Scheduled Tasks

Append the following to crontab (edit path):

```bash
* * * * * cd /home/ubuntu/purrfect-cloud && php artisan schedule:run >> /dev/null 2>&1
```

##### (Optional) Start the Server and Send the IP on reboots

Append the following to crontab (edit path):
```bash
# Start Server
@reboot cd /home/ubuntu/purrfect-cloud && screen -dmS server php artisan serve --host 0.0.0.0

# Send Server IP Address
@reboot cd /home/ubuntu/purrfect-cloud && php artisan app:send-server-address >> /dev/null 2>&1
```
[Regular - Continue to Adding a User](#adding-a-user)

### Adding a User
If payments are disabled, you will need to add the user manually. To add a user, you need to add a subscription, you will be prompted to create the user if it doesn't exist. Get the user's Telegram ID

```bash
php artisan app:update-account-subscription {user_id} {date}
```
e.g
```bash
php artisan app:update-account-subscription 87654321 2030-01-01
```

### Updating
Simply run the following commands to update the application.

##### Nginx Installation - Change Working Directory
```bash
cd /var/www/purrfect-cloud
```

##### Regular Installation - Change Working Directory (edit path)
```bash
cd ~/purrfect-cloud
```

##### Pull Changes and Update
```bash
git pull && composer i && pnpm i && pnpm run build && php artisan migrate --seed --force
```

### Cloud Telegram Session (Optional)
A telegram session will be used to refetch webAppData and prevent disconnections. Enable Telegram Sessions inside .env

Entry | Description
--- | ---
`ENABLE_TELEGRAM_SESSIONS` | Telegram Sessions are Enabled

### Proxy (Optional)
To use proxy, you need to enable it in .env, obtain your API Key from WebShare and save it inside .env

Entry | Description
--- | ---
`FARMER_PROXY_ENABLED` | Proxy is enabled
`FARMER_PROXY_API_KEY` | WebShare Proxy API Key

### Payments (Optional)
Payments work with Paystack, enable payments in .env, get your Paystack public and secret key then save it inside .env

Entry | Description
--- | ---
`FARMER_ENABLE_PAYMENTS` | Payments are enabled
`FARMER_SUBSCRIPTION_AMOUNT` | Subscription Amount
`PAYSTACK_PUBLIC_KEY` | Paystack Public Key
`PAYSTACK_SECRET_KEY` | Paystack Secret Key

