FROM php:8.1-cli

# installing cron package
RUN apt-get update && apt-get -y install cron

# installing PHP PDO extension to talk to MySQL
#RUN docker-php-ext-install mysqli

# putting our test PHP script somewhere in the filesystem
RUN mkdir /cron_scripts
WORKDIR /cron_scripts
COPY check_olt.php /cron_scripts
COPY entry.sh /entry.sh 

# creating the log file that will be written to at each cron iteration
RUN touch zte_check_cron.log 

# copy the crontab in a location where it will be parsed by the system
COPY ./crontab /etc/cron.d/crontab
# owner can read and write into the crontab, group and others can read it
RUN chmod 0644 /etc/cron.d/crontab
# running our crontab using the binary from the package we installed
RUN /usr/bin/crontab /etc/cron.d/crontab
