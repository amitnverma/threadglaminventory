export function optionalAuth(req, res, next) {
  const adminPassword = process.env.ADMIN_PASSWORD;
  if (!adminPassword) return next();

  const provided = req.headers['x-admin-password'] || req.query.admin_password;
  if (provided === adminPassword) return next();

  return res.status(401).json({ error: 'Invalid admin password' });
}
