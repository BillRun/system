#!/bin/bash

PWD="";
LAST_DAY="`date -d '-2 days' +'%Y-%m-%dT%H:%M:%S%:z'`"
NOW="`date +'%Y-%m-%dT%H:%M:%S%:z'`"

OUTPUT=`mongo billing -ureading -p$PWD --quiet --eval 'db.getCollection("lines").find({ type : "tap3",
  urt : { $gte : ISODate("'$LAST_DAY'"),
        $lt : ISODate("'$NOW'")    },    imsi : /^42508/,
    sdr : { $gt : 0 },
    sending_source : {
        $nin : ["AUTPT", "BGR01", "BLRMD", "HRVVI", "LIEMK", "SVNSM", "MKDNO", "SRBNO", "CHNCT", "THACA", "THACT", "USAW6", "GEOGC", "ARGTM", "BRAV1", "BRAV2", "BRAV3", "MEXMS", "BRATC", "UKRUM", "DNKDM", "NORTM", "SWEEP", "CHEDX", "RUSNW", "MNEPM", "PHLSR", "CYPPT", "HKGSM", "ALBVF", "CZECM", "DEUD2", "ESPAT", "GBRVF", "GHAGT", "GRCPF", "HUNVR", "IRLEC", "ITAOM", "NLDLT", "NZLBS", "PRTTL", "ROMMF", "TURTS", "ZAFVC", "BELFT", "FRAFR", "LUXFT", "POLFT", "SVKFT", "SVKGT","LTUMT", "LVABT", "LKADG", "ESTRE", "FINRL", "AREDU", "USACG", "CANBM", "CANTS", "URYAN", "AZEAC", "IND10", "IND11", "IND12", "IND14", "INDA1", "INDA2", "INDA3", "INDA4", "INDA5", "INDA6", "INDA7", "INDA8", "INDA9", "INDAT", "INDBL", "INDH1", "INDJB", "INDJH", "INDMT", "INDSC", "IND15", "IND16", "AUSVF", "BELHB", "PERVG", "VNMVT", "ISLNO", "ISRCL" ] }}).forEach(function(l){
         print(l.urt+","+(l.sid ? l.sid : "" )+","+l.imsi+","+l.sending_source+","+l.sdr);
        })'`;


HEADER="urt,sid,imsi,sending_source"
if [ -n "$OUTPUT" ]; then
    FILENAME="`date +%Y%m%d-%H%I`"_TAP3_with_costs.csv
    echo -e "$HEADER\n$OUTPUT" > "/tmp/$FILENAME"
    echo "Report is attached as  $FILENAME" | mail -s "roaming use without agreement with cost from TAP3" -a "/tmp/$FILENAME" skushnir@golantelecom.co.il  mwahab@golantelecom.co.il  sdahan@golantelecom.co.il  rgolan@golantelecom.co.il eran.uzan@billrun.com
fi
