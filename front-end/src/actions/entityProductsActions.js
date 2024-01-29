export const entityProductRemove = (itemName, path, name) => ({
  type: `${itemName.toUpperCase()}_PRODUCTS_REMOVE`,
  path,
  name,
});

export const entityProductsRateInit = (itemName, service, path) => ({
  type: `${itemName.toUpperCase()}_PRODUCTS_RATE_INIT`,
  product: service,
  path,
});


export const entityProductsRateAdd = (itemName, path) => ({
  type: `${itemName.toUpperCase()}_PRODUCTS_RATE_ADD`,
  path,
});

export const entityProductsRateRemove = (itemName, path, index) => ({
  type: `${itemName.toUpperCase()}_PRODUCTS_RATE_REMOVE`,
  path,
  index,
});

export const entityProductsRateUpdate = (itemName, path, value) => ({
  type: `${itemName.toUpperCase()}_PRODUCTS_RATE_UPDATE`,
  path,
  value,
});


export const entityProductsRateUpdateTo = (itemName, path, index, value) => ({
  type: `${itemName.toUpperCase()}_PRODUCTS_RATE_UPDATE_TO`,
  path,
  index,
  value,
});
