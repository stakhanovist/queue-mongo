#!/usr/bin/env bash
#
# @author leodido - git.io/leodido - @leodido

# variables
IMG=mongo
TAGS=("2.6" "3.0" "3.0.4")
PORT=27017
DATA_PATH=${PWD}/data/mongo
DOCKER_BIN=$(which docker 2>/dev/null)

# functions
mongorm() {
    echo "Removing ${1} container and data ..."
    # remove mongo container
    ${DOCKER_BIN} rm -f $1 &>/dev/null || true

    # remove mongo data
    ${DOCKER_BIN} run --name alpine-rm -v ${DATA_PATH}:/tmp gliderlabs/alpine sh -c 'rm -rf /tmp/*'
    ${DOCKER_BIN} rm -f alpine-rm &>/dev/null || true
    echo "Done."
}

if [ -z "${DOCKER_BIN}" ] ; then
     echo "Please install docker."
     exit 1
fi

if [[ ${CI} ]] ; then
    echo 'ciao'
else
    # test library against <local php, local mongo php ext, mongodb x>

    # todo: iterate for various mongo versions/tags
    TAG=3.0.4
    NAME="${IMG}-${TAG}"
    (${DOCKER_BIN} run --name ${NAME} -v ${DATA_PATH}:/data/db -p ${PORT} -d "${IMG}:${TAG}" --logpath /data/db/start.log) &>/dev/null

    # Wait until mongo logs that it is ready or timeout after 30 seconds
    COUNTER=0
    [ -f ${DATA_PATH}/start.log ] && grep -q 'waiting for connections on port' ${DATA_PATH}/start.log
    while [[ $? -ne 0 ]] ; do
        if [[ ${COUNTER} -lt 30 ]] ; then
            sleep 2
            let COUNTER+=2
            echo "Waiting for ${NAME} to initialize ... ($(printf %02d ${COUNTER}) seconds so far)"
            grep -q 'waiting for connections on port' ${DATA_PATH}/start.log
        else
            mongorm ${NAME}
            exit 1
        fi
    done

    IP_ADDRESS=$(${DOCKER_BIN} inspect -f '{{ .NetworkSettings.IPAddress }}' ${NAME})
    printf "\nAddress: ${IP_ADDRESS}:${PORT}\n\n"
    MONGODB_HOST=${IP_ADDRESS} MONGODB_PORT=${PORT} vendor/bin/phpunit
    printf "\n"

    mongorm ${NAME}
fi
