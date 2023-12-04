/**
 * this script revert the config to the previous revision
 */
var _cfg_before = db.config.find({}).sort({_id:-1}).limit(1).skip(1)[0]
delete _cfg_before['_id'];
_cfg_before['revert'] = ISODate();
db.config.insert(_cfg_before);