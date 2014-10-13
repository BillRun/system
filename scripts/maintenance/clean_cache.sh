#/bin/bash
## script to clean all golan memcached servers

echo 'flush_all' | nc con01.gt.local 11211
echo 'flush_all' | nc con02.gt.local 11211
echo 'flush_all' | nc con03.gt.local 11211 
