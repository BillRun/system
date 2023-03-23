FROM ubuntu:latest

WORKDIR /app

RUN apt-get update && apt-get install -y \
    python3 python3-pip \
    fonts-liberation libappindicator3-1 libasound2 libatk-bridge2.0-0 \
    libnspr4 libnss3 lsb-release xdg-utils libxss1 libdbus-glib-1-2 \
    curl unzip wget \
    xvfb

RUN wget https://chromedriver.storage.googleapis.com/111.0.5563.64/chromedriver_linux64.zip && \
    unzip chromedriver_linux64.zip -d /usr/bin && \
    chmod +x /usr/bin/chromedriver && \
    rm chromedriver_linux64.zip

RUN wget https://dl.google.com/linux/direct/google-chrome-stable_current_amd64.deb && \
    apt-get install -y libgbm1 libu2f-udev libvulkan1 && \
    dpkg -i google-chrome-stable_current_amd64.deb

RUN pip3 install selenium==4.8.2

COPY . .
RUN ls
CMD ["python3","test.py"]
