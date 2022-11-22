from .revision_info_schema import REVISION_INFO_SCHEMA

PLANS_GET_SCHEMA = {
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
                        "name": {
                            "type": "string"
                        },
                        "price": {
                            "type": "array",
                            "items": [
                                {
                                    "type": "object",
                                    "properties": {
                                        "price": {
                                            "type": "integer"
                                        },
                                        "from": {
                                            "type": "integer"
                                        },
                                        "to": {
                                            "type": "string"
                                        }
                                    },
                                    "required": [
                                        "price",
                                        "from",
                                        "to"
                                    ]
                                }
                            ]
                        },
                        "description": {
                            "type": "string"
                        },
                        "recurrence": {
                            "type": "object",
                            "properties": {
                                "frequency": {
                                    "type": "integer"
                                },
                                "start": {
                                    "type": "integer"
                                }
                            },
                            "required": [
                                "frequency",
                                "start"
                            ]
                        },
                        "upfront": {
                            "type": "boolean"
                        },
                        "connection_type": {
                            "type": "string"
                        },
                        "rates": {
                            "type": "array",
                            "items": {}
                        },
                        "prorated_start": {
                            "type": "boolean"
                        },
                        "prorated_end": {
                            "type": "boolean"
                        },
                        "prorated_termination": {
                            "type": "boolean"
                        },
                        "charging_type": {
                            "type": "string"
                        },
                        "creation_time": {
                            "type": "string"
                        },
                        "revision_info": REVISION_INFO_SCHEMA
                    },
                    "required": [
                        "_id",
                        "from",
                        "to",
                        "name",
                        "price",
                        "description",
                        "recurrence",
                        "upfront",
                        "connection_type",
                        "rates",
                        "prorated_start",
                        "prorated_end",
                        "prorated_termination",
                        "charging_type",
                        "creation_time",
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

PLANS_POST_SCHEMA = {
    "$schema": "http://json-schema.org/draft-04/schema#",
    "type": "object",
    "properties": {
        "status": {
            "type": "integer"
        },
        "details": {
            "type": "boolean"
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
                "name": {
                    "type": "string"
                },
                "price": {
                    "type": "array",
                    "items": [
                        {
                            "type": "object",
                            "properties": {
                                "price": {
                                    "type": "integer"
                                },
                                "from": {
                                    "type": "integer"
                                },
                                "to": {
                                    "type": "string"
                                }
                            },
                            "required": [
                                "price",
                                "from",
                                "to"
                            ]
                        }
                    ]
                },
                "description": {
                    "type": "string"
                },
                "recurrence": {
                    "type": "object",
                    "properties": {
                        "frequency": {
                            "type": "integer"
                        },
                        "start": {
                            "type": "integer"
                        }
                    },
                    "required": [
                        "frequency",
                        "start"
                    ]
                },
                "upfront": {
                    "type": "boolean"
                },
                "connection_type": {
                    "type": "string"
                },
                "rates": {
                    "type": "array",
                    "items": {}
                },
                "prorated_start": {
                    "type": "boolean"
                },
                "prorated_end": {
                    "type": "boolean"
                },
                "prorated_termination": {
                    "type": "boolean"
                },
                "charging_type": {
                    "type": "string"
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
                }
            },
            "required": [
                "_id",
                "from",
                "to",
                "name",
                "price",
                "description",
                "recurrence",
                "upfront",
                "connection_type",
                "rates",
                "prorated_start",
                "prorated_end",
                "prorated_termination",
                "charging_type",
                "creation_time"
            ]
        }
    },
    "required": [
        "status",
        "details",
        "entity"
    ]
}
