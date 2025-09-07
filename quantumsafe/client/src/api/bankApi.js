import axios from 'axios';

// IMPORTANT: Replace with your actual server IP address during development.
// For Android Emulator, this is typically 10.0.2.2
// For physical device, it's your computer's local network IP.
const API_URL = 'http://10.0.2.2:8000/bank';

const apiClient = axios.create({
  baseURL: API_URL,
  headers: {
    'Content-Type': 'application/json',
  },
});

export const uploadAndAnalyzeCsv = async (file) => {
  const formData = new FormData();
  formData.append('file', {
    uri: file.uri,
    type: file.type,
    name: file.name,
  });

  try {
    const response = await apiClient.post('/upload', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
    return response.data;
  } catch (error) {
    console.error('Error uploading file:', error.response?.data || error.message);
    throw error;
  }
};

export const getLatestTransactions = async () => {
  try {
    const response = await apiClient.get('/transactions?limit=25');
    return response.data;
  } catch (error) {
    console.error('Error fetching transactions:', error.response?.data || error.message);
    throw error;
  }
};