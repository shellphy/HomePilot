function syncInputValue(context, path, value) {
  const segments = path.replace(/\[(\d+)\]/g, '.$1').split('.');
  const field = segments.pop();
  const target = segments.reduce((data, segment) => data[segment], context.data);

  target[field] = value;
}

module.exports = { syncInputValue };
