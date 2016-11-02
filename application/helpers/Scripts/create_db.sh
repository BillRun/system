#mongo $1 -u[MONGO_ADMIN_USER] -p[MONGO_ADMIN_PASS] --eval "db.createUser({user: '$2',pwd: '$3', roles: [{role: '$4', db: '$1'}]})"
mongo $1 --eval "db.createUser({user: '$2',pwd: '$3', roles: [{role: '$4', db: '$1'}]})"
