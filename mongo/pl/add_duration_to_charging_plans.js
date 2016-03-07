db.plans.find({"type": "charging"}).forEach(function (plan) { 
  db.plans.update({"_id": plan._id},
                  {"$set":
                   {
                     "period":
                     {
                       "unit": "months",
                       "duration": 12
                     }
                   }
                  });
});
