# Docker configuration

## Description

This Readme describe how to use docker and docker-compose  in order to start Billrun application for testing purposes

## Start the docker compose stack

1. Create the log file in order to overcome permission issue and crash

    ```bash
    DEBUG_LOG_DIR=../../logs/container
    mkdir ${DEBUG_LOG_DIR} -p
    touch ${DEBUG_LOG_DIR}/debug.log && chmod 666 ${DEBUG_LOG_DIR}/debug.log
    ```

1. Build the image

    ```bash
    docker-compose  -f docker-compose-php73.yml  build
    ```

1. Start the docker-compose stack

    ```bash
    docker-compose  -f docker-compose-php73.yml  up
    ```

## Stop the docker compose stack

1. Stop the stack and delete docker created volumes

    ```bash
    docker-compose  -f docker-compose-php73.yml  down -v
    ```