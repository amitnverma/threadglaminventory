import axios from 'axios';

const api = axios.create({
  baseURL: '/api',
  headers: { 'Content-Type': 'application/json' },
});

api.interceptors.request.use((config) => {
  const password = localStorage.getItem('admin_password');
  if (password) config.headers['x-admin-password'] = password;
  return config;
});

export default api;

export const uploadFile = async (type, id, file, caption = '') => {
  const formData = new FormData();
  formData.append('file', file);
  if (caption) formData.append('caption', caption);
  const { data } = await api.post(`/attachments/${type}/${id}`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
  return data;
};

export const getImageUrl = (path) => (path ? `/uploads/${path}` : null);
