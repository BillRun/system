from .revision_info_schema import REVISION_INFO_SCHEMA

DISCOUNT_POST_SCHEMAS = {
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
                "key": {
                    "type": "string"
                },
                "description": {
                    "type": "string"
                },
                "params": {
                    "type": "object",
                    "properties": {
                        "min_subscribers": {
                            "type": ["string", "integer"]
                        },
                        "max_subscribers": {
                            "type": ["string", "integer"]
                        }
                    },
                    "required": [
                        "min_subscribers",
                        "max_subscribers"
                    ]
                },
                "type": {
                    "type": "string"
                },
                "proration": {
                    "type": "string"
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
                "key",
                "description",
                "params",
                "type",
                "proration",
                "to",
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

DISCOUNT_GET_SCHEMAS = {
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
                        "key": {
                            "type": "string"
                        },
                        "description": {
                            "type": "string"
                        },
                        "params": {
                            "type": "object",
                            "properties": {
                                "min_subscribers": {
                                    "type": ["string", "integer"]
                                },
                                "max_subscribers": {
                                    "type": ["string", "integer"]
                                }
                            },
                            "required": [
                                "min_subscribers",
                                "max_subscribers"
                            ]
                        },
                        "type": {
                            "type": "string"
                        },
                        "proration": {
                            "type": "string"
                        },
                        "to": {
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
                        "key",
                        "description",
                        "params",
                        "type",
                        "proration",
                        "to",
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
