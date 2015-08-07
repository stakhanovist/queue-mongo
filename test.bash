#!/usr/bin/env bash

# DOCKER_BIN=$(which docker 2> /dev/null)
# if [ -z "${DOCKER_BIN}" ]; then
#     echo "Please install docker"
#     exit 1
# fi

if [[ ${CI} ]]; then
    echo 'ciao'
else
    # test library against <local php, local mongo php ext, mongodb x>
    PORT=27017

    # todo: iterate for various mongo versions
    MID=$(docker run --name mongo-304 -v ${PWD}/data/mongo:/data/db -p ${PORT} -d mongo:3.0.4)

    IP_ADDRESS=$(docker inspect -f '{{ .NetworkSettings.IPAddress }}' mongo-304)
    echo "Creating server at ${IP_ADDRESS}:${PORT} ..."
    sleep 7
    MONGODB_HOST=${IP_ADDRESS} MONGODB_PORT=${PORT} vendor/bin/phpunit

    # always remove it
    docker rm -f mongo-304 &>/dev/null || true

    # sudo rm -rf data/mongo/*
fi
