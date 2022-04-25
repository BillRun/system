# Docker configuration

## Description

This Readme describes how to use docker in order to start Billrun application for testing purposes

## Run prior to the "run_docker" command

1. Move to the billrun docker folder

    ```bash
    cd <BillRun dir>/docker/billrun-docker
    ```

2. Stop and remove existing containers if you already used the newer version of the BillRun docker

    ```bash
    docker-compose down
    ```

3. Stop your local mongod if it is installed

    ```bash
    sudo systemctl stop mongodb
    ```

4. Backup and then remove any existing DB files

    ```bash
    rm -r ../persist
    ```

## Run the docker

1. Start the containers

    ```bash
    ./run_docker.sh <BillRun dir>
    ```

## Post "run_docker" commands/actions

1. Find out the billrun-mongo container ip

    ```bash
    docker inspect billrun-mongo | grep IPAddress | grep "\."
    ```

2. Edit conf/dev.ini and set db.host to <IP>:27017 where <IP> is the above IP address
