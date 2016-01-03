#!/bin/bash

# #import 012 files
# php ./public/process.php 012 workspace/012/INT_KVZ_GLN_MABAL_000001_201207311333.DAT
# php ./public/process.php 012 workspace/012/INT_KVZ_GLN_MABAL_000003_201210171437.DAT
# php ./public/process.php 012 workspace/012/INT_KVZ_GLN_MABAL_000004_201210171443.DAT
# php ./public/process.php 012 workspace/012/INT_KVZ_GLN_MABAL_000005_201210171449.DAT
# php ./public/process.php 012 workspace/012/INT_KVZ_GLN_MABAL_000006_201210171457.DAT
# php ./public/process.php 012 workspace/012/INT_KVZ_GLN_MABAL_000007_201211041212.DAT
# php ./public/process.php 012 workspace/012/INT_KVZ_GLN_MABAL_000008_201212050953.DAT
#
# #import 018 files
# php ./public/process.php 018 workspace/018/SXFN_FINTL_ID000001_201209201609.DAT
# php ./public/process.php 018 workspace/018/SXFN_FINTL_ID000002_201209201618.DAT
# php ./public/process.php 018 workspace/018/SXFN_FINTL_ID000003_201209201624.DAT
# php ./public/process.php 018 workspace/018/SXFN_FINTL_ID000004_201209201625.DAT
# php ./public/process.php 018 workspace/018/SXFN_FINTL_ID000005_201209201632.DAT
# php ./public/process.php 018 workspace/018/SXFN_FINTL_ID000006_201209201634.DAT
#
# #import 013 files
# # php ./public/process.php 013 workspace/013/I001800007.I001800007
# # php ./public/process.php 013 workspace/013/I001800006.I001800006
# # php ./public/process.php 013 workspace/013/I001800005.I001800005
# # php ./public/process.php 013 workspace/013/I001800003.I001800003
# # php ./public/process.php 013 workspace/013/I001800002.BK1D56

#processs al the files in workspace
php ./public/receive.php ./workspace

#sleep 1;
# calculate each line
php ./public/calc.php

#sleep 1;
# aggregate each line with the account and subscriber
php ./public/aggregate.php ilds 201212ilds2

# generate xml + csv
php ./public/generate.php ilds 201212ilds2
