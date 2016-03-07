db.plans.find({"type": "charging", "$or": [{"recurring": 0}, {"recurring": {"$exists": 0}}]}).forEach(function (plan) {
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
