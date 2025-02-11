# Domain Monitor

A PHP script that monitors domain availability and sends email notifications when a domain becomes available for registration.

## Features

- Monitors domain availability using WHOIS protocol
- Sends email notifications via SMTP using PHPMailer
- Comprehensive logging with rotation using Monolog
- Configurable monitoring intervals
- Separate configuration file for easy deployment

## Requirements

- PHP 7.4 or higher
- Composer for dependency management
- SMTP server access for sending emails
- Write permissions for log directory

## Installation

1. Clone the repository or download the files:
```bash
git clone https://github.com/suvash23/domain-monitor.git
cd domain-monitor
```

2. Install dependencies using Composer:
```bash
composer install
```

3. Create the logs directory:
```bash
mkdir logs
chmod 777 logs
```

4. Copy the configuration file and update with your settings:
```bash
cp config.php.example config.php
```

## Configuration

Open `config.php` and update the following settings:

### Domain Settings
```php
'domain' => [
    'name' => 'example.com',          // Domain to monitor
    'check_interval' => 300,          // Check interval in seconds
    'whois_server' => 'whois.internic.net',
    'whois_port' => 43,
    'whois_timeout' => 10
]
```

### Email Settings
```php
'email' => [
    'recipient' => 'your@email.com',  // Notification recipient
    'smtp' => [
        'host' => 'smtp.example.com', // SMTP server
        'port' => 587,                // SMTP port
        'username' => 'your_username',
        'password' => 'your_password',
        'encryption' => 'tls',
        'from_email' => 'from@example.com',
        'from_name' => 'Domain Monitor'
    ]
]
```

### Logging Settings
```php
'logging' => [
    'directory' => __DIR__ . '/logs',
    'filename' => 'domain_monitor.log',
    'max_files' => 30,               // Number of days to keep logs
    'level' => 'info',               // Log level (debug, info, warning, error)
    'console_output' => true         // Also output to console
]
```

## Usage

Run the script using PHP CLI:
```bash
php domain-monitor.php
```

The script will:
1. Check the domain availability at the configured interval
2. Log all activities to the specified log file
3. Send an email notification when the domain becomes available
4. Continue running until the domain becomes available or the script is terminated

## Logging

Logs are stored in the `logs` directory with the following format:
- Daily rotation with date suffix
- Keeps logs for the specified number of days
- Logs include timestamps and severity levels
- Both successful operations and errors are logged

Example log entry:
```
[2025-02-11 10:15:30] DomainMonitor.INFO: Checking availability for domain: example.com
```

## Error Handling

The script includes comprehensive error handling:
- WHOIS server connection failures
- Email sending errors
- Configuration validation
- File system errors

All errors are:
- Logged to the log file
- Displayed in console (if enabled)
- Handled gracefully to prevent script termination

## Security Considerations

1. Protect your `config.php` file:
```bash
chmod 600 config.php
```

2. Store the script in a non-public directory
3. Use strong SMTP credentials
4. Consider using environment variables for sensitive data

## Troubleshooting

1. Check the logs in the `logs` directory
2. Verify SMTP settings
3. Ensure proper permissions on the logs directory
4. Validate WHOIS server accessibility

Common issues:
- SMTP authentication failures
- Permission denied for log writing
- WHOIS server rate limiting
- Invalid configuration values

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## License

This project is licensed under the MIT License - see the LICENSE file for details.
