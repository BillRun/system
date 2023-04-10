#!/bin/bash

PWD="";
LAST_HOUR="`date -d '-1 hours' +'%Y-%m-%dT%H:%M:%S%:z'`"
NOW="`date +'%Y-%m-%dT%H:%M:%S%:z'`"

OUTPUT=`mongo billing -ureading -p$PWD --quiet --eval 'rs.slaveOk();db.getCollection("lines").find({ source : "nrtrde",
    urt : {        $gte : ISODate("'$LAST_HOUR'"),
        $lt : ISODate("'$NOW'")    },
    imsi : /^42508/,
    sender : {
        $nin : ["POL03", "BELMO", "FRAF1", "AUTPT", "BGR01", "BLRMD", "HRVVI", "LIEMK", "SVNSM", "MKDNO", "SRBNO", "CHNCT", "THACA", "THACT", "USAW6", "GEOGC", "ARGTM", "BRAV1", "BRAV2", "BRAV3", "MEXMS", "BRATC", "UKRUM", "DNKDM", "NORTM", "SWEEP", "CHEDX", "RUSNW", "MNEPM", "PHLSR", "CYPPT", "HKGSM", "ALBVF", "CZECM", "DEUD2", "ESPAT", "GBRVF", "GHAGT", "GRCPF", "HUNVR", "IRLEC", "ITAOM", "NLDLT", "NZLBS", "PRTTL", "ROMMF", "TURTS", "ZAFVC", "BELFT", "FRAFR", "LUXFT", "POLFT", "SVKFT","SVKGT", "LTUMT", "LVABT", "LKADG", "ESTRE", "FINRL", "AREDU", "USACG", "CANBM", "CANTS", "URYAN", "AZEAC", "IND10", "IND11", "IND12", "IND14", "INDA1", "INDA2", "INDA3", "INDA4", "INDA5", "INDA6", "INDA7", "INDA8", "INDA9", "INDAT", "INDBL", "INDH1", "INDJB", "INDJH", "INDMT", "INDSC", "IND15", "IND16", "AUSVF", "BELHB", "PERVG", "VNMVT", "ISLNO", "ISRCL" ] }}).forEach(function(l){
         print(l.urt+","+(l.sid ? l.sid : "" )+","+l.imsi+","+l.sender);
        })'`;


HEADER="urt,sid,imsi,sender"
if [ -n "$OUTPUT" ]; then
    FILENAME="`date +%Y%m%d-%H%I`"_NRTRDE_last_hour.csv
    echo -e "$HEADER\n$OUTPUT" > "/tmp/$FILENAME"
    echo "Report is attached as  $FILENAME"| mail -s "Roaming use without agreement from NRTRDE last hour" -a "/tmp/$FILENAME" skushnir@golantelecom.co.il  mwahab@golantelecom.co.il sdahan@golantelecom.co.il rgolan@golantelecom.co.il eran.uzan@billrun.com
fi
