FROM codeceptjs/codeceptjs
#ENV NODE_PATH=/codecept/node_modules
RUN npm i "codeceptjs-chai" --force --prefix /codecept
RUN curl -fsSL https://get.docker.com -o get-docker.sh && sh get-docker.sh