 
#include <stdio.h>
#include <stdlib.h>
#include <sys/types.h>
#include <unistd.h>

int main()
{
    setuid( 0 );   // you can set it at run time also    
    system( "/sbin/rmmod -f usbserial" );
    sleep(5);
    system( "/sbin/modprobe usbserial" );
    return 0;
 } 
