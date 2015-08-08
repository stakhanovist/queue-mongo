#!/usr/bin/env bash
#
# @author leodido - git.io/leodido - @leodido

# variables
declare DATA_PATH="${DATA_PATH:-${PWD}/data/mongo}"
declare -a TAGS=("2.4" "2.6" "3.0")
declare IMG=mongo
declare -i PORT=27017
declare DOCKER_BIN=$(which docker 2>/dev/null)
declare PHP_BIN=$(which php 2>/dev/null)

# functions
printf_sep() {
    local STR=$1
    local NUM=$2
    local SEP=$(printf "%-${NUM}s" "${STR}")
    echo "${SEP// /${STR}}"
}
mongorm() {
    echo "Removing ${1} container and data ..."
    # remove mongo container
    ${DOCKER_BIN} rm -f $1 &>/dev/null || true

    # remove mongo data
    ${DOCKER_BIN} run --name alpine-rm -v ${DATA_PATH}:/tmp gliderlabs/alpine sh -c 'rm -rf /tmp/*'
    ${DOCKER_BIN} rm -f alpine-rm &>/dev/null || true
}
mongowait() {
    local -i COUNTER=0
    [ -f ${DATA_PATH}/start.log ] && grep -q 'waiting for connections on port' ${DATA_PATH}/start.log
    while [[ $? -ne 0 ]] ; do
        if [[ ${COUNTER} -lt 30 ]] ; then
            sleep 2
            let COUNTER+=2
            echo "Waiting for $1 to initialize ... ($(printf %02d ${COUNTER}) seconds so far)"
            grep -q 'waiting for connections on port' ${DATA_PATH}/start.log
        else
            mongorm $1
            echo "Exit ..."
            exit 124
        fi
    done
}
main() {
    # check docker is installed
    if [ -z "${DOCKER_BIN}" ] ; then
         echo "Please install docker."
         exit 1
    fi
    # check php is installed
    if [ -z "${PHP_BIN}" ] ; then
         echo "Please install PHP."
         exit 1
    fi

    if [[ ${CI} ]] ; then
        # continuous integration
        echo 'ciao'
    else
        # get PHP simple version
        local PHP_VERSION=$(php -r 'echo sprintf("%s.%s", PHP_MAJOR_VERSION, PHP_MINOR_VERSION);')

        # check whether mongo php driver is missing
        local MONGO_EXT_MISSING=$(php -r 'echo extension_loaded("mongo") ? "false" : "true";')
        if [ "${MONGO_EXT_MISSING}" = true ] ; then
            echo -e "Please install MongoDB PHP driver.\nExit ..."
            exit 1
        fi

        # retrieve mongo php driver version
        local MONGO_EXT_VERSION=$(${PHP_BIN} -r 'echo phpversion("mongo"), "\n";')

        # print env info
        printf "PHP %s\nMongoDB PHP driver %s\n" ${PHP_VERSION} ${MONGO_EXT_VERSION}
        printf_sep "=" 80

        # check compatibilities
        # http://docs.mongodb.org/ecosystem/drivers/driver-compatibility-reference/#reference-compatibility-language-php
        MONGO_EXT_VERSION=${MONGO_EXT_VERSION%.*}
        if [ ${PHP_VERSION} = "5.6" ] && { [ ${MONGO_EXT_VERSION} = "1.4" ] || [ ${MONGO_EXT_VERSION} = "1.3" ]; }; then
            echo -e "PHP ${PHP_VERSION} requires a MongoDB PHP driver >= 1.5 ...\nExit"
            exit 1
        fi

        # fixed local php, local mongo php ext, test library against various mongo versions/tags
        for TAG in "${TAGS[@]}"
        do
            # check compatibilities
            # http://docs.mongodb.org/ecosystem/drivers/driver-compatibility-reference/#reference-compatibility-mongodb-php
            if [ ${TAG} = "2.6" ] && [ ${MONGO_EXT_VERSION} = "1.3" ]; then
                echo -e "MongoDB ${TAG} requires a MongoDB PHP driver >= 1.3 ...\nExit"
                exit 1
            fi
             if [ ${TAG} = "3.0" ] && { [ ${MONGO_EXT_VERSION} = "1.5" ] || [ ${MONGO_EXT_VERSION} = "1.4" ] || [ ${MONGO_EXT_VERSION} = "1.3" ]; }; then
                echo -e "MongoDB ${TAG} requires a MongoDB PHP driver >= 1.5 ...\nExit"
                exit 1
            fi

            [[ "${TAG}" != "${TAGS[@]:0:1}" ]] && printf_sep "=" 80
            echo "Test against ${IMG}:${TAG}"

            # Create container
            local NAME="${IMG}-${TAG}"
            (${DOCKER_BIN} run --name ${NAME} -v ${DATA_PATH}:/data/db -p ${PORT} -d "${IMG}:${TAG}" --logpath /data/db/start.log) &>/dev/null

            # Wait until container logs that it is ready, or timeout after 30 seconds
            mongowait ${NAME}

            # Retrieve container IP address
            local IP_ADDRESS=$(${DOCKER_BIN} inspect -f '{{ .NetworkSettings.IPAddress }}' ${NAME})
            printf "\nAddress: ${IP_ADDRESS}:${PORT}\n\n"

            # Run test suite
            MONGODB_HOST=${IP_ADDRESS} MONGODB_PORT=${PORT} vendor/bin/phpunit
            local TESTS_EXIT_CODE=$?
            printf "\n"

            # Cleanup
            mongorm ${NAME}
            if [ ${TESTS_EXIT_CODE} -ne 0 ] ; then
                echo "Exit ..."
                exit ${TESTS_EXIT_CODE}
            fi
            echo "Done ..."
        done
    fi
}

main