import { setupDatabase } from '../config/db.js';

setupDatabase()
  .then(() => {
    console.log('Database setup complete.');
    process.exit(0);
  })
  .catch((err) => {
    console.error('Database setup failed:', err.message);
    process.exit(1);
  });
