# Email reports

Weekly energy reports delivered to your inbox.

## Setup

Symlink the web part of the emailreport module into emoncms/Modules, if not using Raspberry Pi replace 'pi' with your home folder name:

    ln -s /home/user/emailreport/emailreport-module /var/www/emoncms/Modules/emailreport

Crontab:

    0 9 * * 1 php /home/user/emailreport/weekly-cron.php >> /var/log/emoncms/emailreport.log
