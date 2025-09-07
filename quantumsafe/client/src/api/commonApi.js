import axios from 'axios';

// Use the same base URL configuration
const API_URL = 'http://10.0.2.2:8000/common';

const apiClient = axios.create({
  baseURL: API_URL,
  headers: {
    'Content-Type': 'application/json',
  },
});

export const analyzeChatbotRequest = async (data) => {
  try {
    const response = await apiClient.post('/chatbot', data);
    return response.data;
  } catch (error) {
    console.error('Error in chatbot analysis:', error.response?.data || error.message);
    throw error;
  }
};

export const analyzeMicrofraud = async (transactions_text) => {
  try {
    const response = await apiClient.post('/microfraud', { transactions_text });
    return response.data;
  } catch (error) {
    console.error('Error in microfraud analysis:', error.response?.data || error.message);
    throw error;
  }
};

export const analyzeImage = async (file, qr_text = '', section = 'screenshot') => {
  const formData = new FormData();
  formData.append('file', {
    uri: file.uri,
    type: file.type,
    name: file.name,
  });
  formData.append('section', section);
  if (qr_text) {
    formData.append('qr_text', qr_text);
  }

  try {
    const response = await apiClient.post('/analyze-image', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
    return response.data;
  } catch (error) {
    console.error('Error analyzing image:', error.response?.data || error.message);
    throw error;
  }
};

export const getResults = async (filters) => {
  try {
    // filters can be { feature: 'chatbot', order: 'new', page: 1 }
    const response = await apiClient.get('/results', { params: filters });
    return response.data;
  } catch (error) {
    console.error('Error fetching results:', error.response?.data || error.message);
    throw error;
  }
};
