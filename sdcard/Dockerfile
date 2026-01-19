FROM ubuntu:24.04
RUN apt-get update && \
    apt-get install -y parted udev e2fsprogs sudo
COPY pishrink.sh /usr/local/bin/pishrink
RUN chmod +x /usr/local/bin/pishrink
ENTRYPOINT ["/usr/local/bin/pishrink"]
