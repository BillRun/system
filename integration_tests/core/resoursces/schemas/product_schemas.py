from .revision_info_schema import REVISION_INFO_SCHEMA

PRODUCT_GET_SCHEMA = {
  "$schema": "http://json-schema.org/draft-04/schema#",
  "type": "object",
  "properties": {
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
            "add_to_retail": {
              "type": "boolean"
            },
            "creation_time": {
              "type": "string"
            },
            "description": {
              "type": "string"
            },
            "from": {
              "type": "string"
            },
            "invoice_label": {
              "type": "string"
            },
            "key": {
              "type": "string"
            },
            "pricing_method": {
              "type": "string"
            },
            "rates": {
              "type": "object",
              "patternProperties": {
                "^.*$": {
                  "type": "object",
                  "properties": {
                    "BASE": {
                      "type": "object",
                      "properties": {
                        "rate": {
                          "type": "array",
                          "items": [
                            {
                              "type": "object",
                              "properties": {
                                "from": {
                                  "type": "integer"
                                },
                                "interval": {
                                  "type": "integer"
                                },
                                "price": {
                                  "type": "string"
                                },
                                "to": {
                                  "type": "string"
                                },
                                "uom_display": {
                                  "type": "object",
                                  "properties": {
                                    "interval": {
                                      "type": "string"
                                    },
                                    "range": {
                                      "type": "string"
                                    }
                                  },
                                  "required": [
                                    "interval",
                                    "range"
                                  ]
                                }
                              },
                              "required": [
                                "from",
                                "interval",
                                "price",
                                "to",
                                "uom_display"
                              ]
                            }
                          ]
                        }
                      },
                      "required": [
                        "rate"
                      ]
                    }
                  },
                  "required": [
                    "BASE"
                  ]
                }
              },
            },
            "revision_info": REVISION_INFO_SCHEMA,
            "tariff_category": {
              "type": "string"
            },
            "tax": {
              "type": "array",
              "items": [
                {
                  "type": "object",
                  "properties": {
                    "taxation": {
                      "type": "string"
                    },
                    "type": {
                      "type": "string"
                    }
                  },
                  "required": [
                    "taxation",
                    "type"
                  ]
                }
              ]
            },
            "to": {
              "type": "string"
            }
          },
          "required": [
            "_id",
            "add_to_retail",
            "creation_time",
            "description",
            "from",
            "invoice_label",
            "key",
            "pricing_method",
            "rates",
            "revision_info",
            "tariff_category",
            "tax",
            "to"
          ]
        }
      ]
    },
    "next_page": {
      "type": "boolean"
    },
    "status": {
      "type": "integer"
    }
  },
  "required": [
    "details",
    "next_page",
    "status"
  ]
}