
var relevant_key = /^KT/;
var today = new Date();

db.rates.update({key: relevant_key, from: {$lte: today}, to: {$gt: today}}, {$set:{'params.out_circuit_group': "2102"}}, {multi: true})

