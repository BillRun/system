mongo "$3" -u"$4" -p"$5" --eval 'db.createUser({user: "$1",pwd: "$2", roles: [{role: "readWrite", db: "$3"}]})'
