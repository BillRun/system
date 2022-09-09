from .revision_info_schema import REVISION_INFO_SCHEMA

SUBSCRIBER_POST_SCHEMA = {
    "$schema": "http://json-schema.org/draft-04/schema#",
    "type": "object",
    "properties": {
        "status": {
            "type": "integer"
        },
        "details": {
            "type": "null"
        },
        "entity": {
            "type": "object",
            "properties": {
                "_id": {
                    "type": "object",
                    "properties": {
                        "$id": {
                            "type": "string"
                        }
                    },
                    "required": [
                        "$id"
                    ]
                },
                "from": {
                    "type": "object",
                    "properties": {
                        "sec": {
                            "type": "integer"
                        },
                        "usec": {
                            "type": "integer"
                        }
                    },
                    "required": [
                        "sec",
                        "usec"
                    ]
                },
                "to": {
                    "type": "object",
                    "properties": {
                        "sec": {
                            "type": "integer"
                        },
                        "usec": {
                            "type": "integer"
                        }
                    },
                    "required": [
                        "sec",
                        "usec"
                    ]
                },
                "aid": {
                    "type": "integer"
                },
                "sid": {
                    "type": "integer"
                },
                "plan": {
                    "type": "string"
                },
                "services": {
                    "type": "array",
                    "items": {}
                },
                "play": {
                    "type": "string"
                },
                "firstname": {
                    "type": "string"
                },
                "lastname": {
                    "type": "string"
                },
                "address": {
                    "type": "string"
                },
                "country": {
                    "type": "string"
                },
                "type": {
                    "type": "string"
                },
                "plan_activation": {
                    "type": "object",
                    "properties": {
                        "sec": {
                            "type": "integer"
                        },
                        "usec": {
                            "type": "integer"
                        }
                    },
                    "required": [
                        "sec",
                        "usec"
                    ]
                },
                "deactivation_date": {
                    "type": "object",
                    "properties": {
                        "sec": {
                            "type": "integer"
                        },
                        "usec": {
                            "type": "integer"
                        }
                    },
                    "required": [
                        "sec",
                        "usec"
                    ]
                },
                "creation_time": {
                    "type": "object",
                    "properties": {
                        "sec": {
                            "type": "integer"
                        },
                        "usec": {
                            "type": "integer"
                        }
                    },
                    "required": [
                        "sec",
                        "usec"
                    ]
                },
                "activation_date": {
                    "type": "object",
                    "properties": {
                        "sec": {
                            "type": "integer"
                        },
                        "usec": {
                            "type": "integer"
                        }
                    },
                    "required": [
                        "sec",
                        "usec"
                    ]
                },
                "plan_deactivation": {
                    "type": "object",
                    "properties": {
                        "sec": {
                            "type": "integer"
                        },
                        "usec": {
                            "type": "integer"
                        }
                    },
                    "required": [
                        "sec",
                        "usec"
                    ]
                }
            },
            "required": [
                "_id",
                "from",
                "to",
                "aid",
                "sid",
                "plan",
                "services",
                "play",
                "firstname",
                "lastname",
                "address",
                "country",
                "type",
                "plan_activation",
                "deactivation_date",
                "creation_time",
                "activation_date",
                "plan_deactivation"
            ]
        }
    },
    "required": [
        "status",
        "details",
        "entity"
    ]
}

SUBSCRIBER_GET_SCHEMA = {
    "$schema": "http://json-schema.org/draft-04/schema#",
    "type": "object",
    "properties": {
        "status": {
            "type": "integer"
        },
        "next_page": {
            "type": "boolean"
        },
        "details": {
            "type": "array",
            "items": [
                {
                    "type": "object",
                    "properties": {
                        "_id": {
                            "type": "object",
                            "properties": {
                                "$id": {
                                    "type": "string"
                                }
                            },
                            "required": [
                                "$id"
                            ]
                        },
                        "from": {
                            "type": "string"
                        },
                        "to": {
                            "type": "string"
                        },
                        "aid": {
                            "type": "integer"
                        },
                        "sid": {
                            "type": "integer"
                        },
                        "plan": {
                            "type": "string"
                        },
                        "services": {
                            "type": "array",
                            "items": {}
                        },
                        "play": {
                            "type": "string"
                        },
                        "firstname": {
                            "type": "string"
                        },
                        "lastname": {
                            "type": "string"
                        },
                        "address": {
                            "type": "string"
                        },
                        "country": {
                            "type": "string"
                        },
                        "type": {
                            "type": "string"
                        },
                        "plan_activation": {
                            "type": "object",
                            "properties": {
                                "sec": {
                                    "type": "integer"
                                },
                                "usec": {
                                    "type": "integer"
                                }
                            },
                            "required": [
                                "sec",
                                "usec"
                            ]
                        },
                        "deactivation_date": {
                            "type": "object",
                            "properties": {
                                "sec": {
                                    "type": "integer"
                                },
                                "usec": {
                                    "type": "integer"
                                }
                            },
                            "required": [
                                "sec",
                                "usec"
                            ]
                        },
                        "creation_time": {
                            "type": "string"
                        },
                        "activation_date": {
                            "type": "object",
                            "properties": {
                                "sec": {
                                    "type": "integer"
                                },
                                "usec": {
                                    "type": "integer"
                                }
                            },
                            "required": [
                                "sec",
                                "usec"
                            ]
                        },
                        "plan_deactivation": {
                            "type": "object",
                            "properties": {
                                "sec": {
                                    "type": "integer"
                                },
                                "usec": {
                                    "type": "integer"
                                }
                            },
                            "required": [
                                "sec",
                                "usec"
                            ]
                        },
                        "revision_info": REVISION_INFO_SCHEMA
                    },
                    "required": [
                        "_id",
                        "from",
                        "to",
                        "aid",
                        "sid",
                        "plan",
                        "services",
                        "play",
                        "firstname",
                        "lastname",
                        "address",
                        "country",
                        "type",
                        "plan_activation",
                        "deactivation_date",
                        "creation_time",
                        "activation_date",
                        "plan_deactivation",
                        "revision_info"
                    ]
                }
            ]
        }
    },
    "required": [
        "status",
        "next_page",
        "details"
    ]
}
