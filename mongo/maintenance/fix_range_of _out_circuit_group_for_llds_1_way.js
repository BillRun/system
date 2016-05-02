
var relevant_key = /^KT/;
var today = new Date();

db.rates.update({key: relevant_key, from: {$lte: today}, to: {$gt: today}}, {$set:{'params.out_circuit_group': [{from: "2100", to: "2102"}, {from: "2499", to: "2499"}, {from: "2120", to: "2122"}]}}, {multi: true})
