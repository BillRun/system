var count = db.plans.find({"type": "charging", "$or": [{"recurring": 0}, {"recurring": {"$exists": 0}}]}).count();
var res = db.plans.update({"type": "charging", "$or": [{"recurring": 0}, {"recurring": {"$exists": 0}}]},
                          {"$set":
                           {
                             "period":
                             {
                               "unit": "months",
                               "duration": 12
                             }
                           }
                          },
                          {multi: true});
if (res.nModified !== count) {
  print("Found " + count + " but only updated " + res.nModified + "!!");
} else {
  print("Modified " + res.nModified + " of " + count + " records!");
}
