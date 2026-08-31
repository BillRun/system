const GroupsInclude = {
  name: "Group name should be unique for all plans and services",
  products: "Products should be unique for all plans and services",
  products_group: 'A products group defines the set of products the include quota applies to. For a usage based quota only products groups with a property type are available',
  shared_desc: "The includes will be shared with all the customer's subscribers. This is useful for family or organization package",
  pooled_desc: 'When the \'Pooled\' option is activated, each subscriber, under the same customer, will \'Add\' its \'Includes\' allocation to the customer pool. The allocation can be either monetary allocation or a volume [call minutes, GBs of data, etc] based.',
  quantityAffected_desc: 'When the \'Quantity Affected\' option is activated, each subscriber, under the same customer, will \'Add\' its \'Includes\' allocation to the customer pool, multiplied by the subscribers\' service quantity. The allocation can be either monetary allocation or a volume [call minutes, GBs of data, etc] based.',
};

export default GroupsInclude;
