if [ -d "$1" ]; then
  BILLRUN_DIR=$1 docker-compose up -d
else
  echo "Error: Directory $1 does not exist."
  exit 1
fi
