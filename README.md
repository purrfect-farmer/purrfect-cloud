<p align="center"><a href="https://t.me/purrfect_community" target="_blank"><img src="resources/images/icon.png" width="192" alt="Purrfect Logo"></a></p>

<h1 style="text-align:center">Purrfect Cloud</h1>

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

##### Change Working Directory to Home or Desired Path
```bash
cd ~
```

##### Clone the repository
```bash
git clone https://github.com/purrfect-farmer/purrfect-cloud.git
```

##### Change Working Directory to Purrfect Cloud
```bash
cd purrfect-cloud
```

##### Install Composer Packages
```bash
composer i
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

##### Extracting Group ID and Topic ID
Before proceeding, obtain your Telegram Group ID and Topic ID by sending a message to a topic in your group and copy the message link.

Then you can extract the Group ID and Topic ID from the link:
`https://t.me/c/{group_id}/{topic_id}/{message_id}`

An example: `https://t.me/c/2322054671/10837/34926`

The Group ID is always the same but the Topic ID changes.

##### Update .env
For Micro: press (`Ctrl+S` then `Ctrl+Q`) to save.
For Nano: press (`Ctrl+S` then `Ctrl+X`) to save.

```bash
micro .env
```

Note: When setting the `TELEGRAM_CHAT_ID`, it should always start with `-100` like `-100{group_id}` e.g `-1002322054671`.

Set the appropriate Topic ID for any key that ends with `_THREAD_ID` e.g `TELEGRAM_CHAT_ANNOUNCEMENT_THREAD_ID`

##### Important Entries
Entry | Description
--- | ---
`TELEGRAM_BOT_TOKEN` | Your Telegram Bot Token
`TELEGRAM_CHAT_ID` | Group ID e.g `-100{group_id}`
`TELEGRAM_CHAT_ANNOUNCEMENT_THREAD_ID` | Announcement Topic ID
`TELEGRAM_CHAT_ERROR_THREAD_ID` | Error Topic ID

##### Additional Entries for Farmers
Entry | Description
--- | ---
`FARMER_EXAMPLE_ENABLED` | Farmer is Enabled
`FARMER_EXAMPLE_THREAD_ID` | Farmer Topic ID

### Cron Job
##### Register a Cron Job for Scheduled Tasks

```bash
crontab -e
```

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


### Adding a User
If payments are disabled, you will need to add the user manually. To add a user, you need to add a subscription, you will be prompted to create the user if it doesn't exist. Get the user's Telegram Id

```bash
php artisan app:update-account-subscription {user_id} {date}
```
e.g
```bash
php artisan app:update-account-subscription 87654321 2030-01-01
```

### Updating
Simply run the following commands to update the application.

##### Change Working Directory (edit path)
```bash
cd ~/purrfect-cloud
```

##### Pull Changes and Update
```bash
git pull && composer i && php artisan migrate --seed --force
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

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[WebReinvent](https://webreinvent.com/)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Jump24](https://jump24.co.uk)**
- **[Redberry](https://redberry.international/laravel/)**
- **[Active Logic](https://activelogic.com)**
- **[byte5](https://byte5.de)**
- **[OP.GG](https://op.gg)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
