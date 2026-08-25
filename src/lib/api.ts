/**
 * API Client for USMS Backend
 */

export interface ApiResponse<T = any> {
  status: 'success' | 'error';
  message: string;
  data: T;
}

const API_BASE = '/api/v1';

async function request<T = any>(
  endpoint: string,
  options: RequestInit = {}
): Promise<ApiResponse<T>> {
  // Normalize endpoint to remove leading slash
  const cleanEndpoint = endpoint.startsWith('/') ? endpoint.slice(1) : endpoint;
  const url = `${API_BASE}/${cleanEndpoint}`;

  const defaultHeaders: Record<string, string> = {
    'Accept': 'application/json',
  };

  // Only set Content-Type if body is not FormData
  if (!(options.body instanceof FormData)) {
    defaultHeaders['Content-Type'] = 'application/json';
  }

  const response = await fetch(url, {
    ...options,
    credentials: 'include',
    headers: {
      ...defaultHeaders,
      ...options.headers,
    },
  });

  let data: ApiResponse<T>;
  try {
    data = await response.json();
  } catch (err) {
    throw new Error(`Failed to parse server response (${response.status} ${response.statusText})`);
  }

  if (!response.ok || data.status === 'error') {
    throw new Error(data.message || `Request failed with status ${response.status}`);
  }

  return data;
}

export const api = {
  get<T = any>(endpoint: string, params?: Record<string, any>): Promise<ApiResponse<T>> {
    let url = endpoint;
    if (params) {
      const searchParams = new URLSearchParams();
      Object.entries(params).forEach(([key, val]) => {
        if (val !== undefined && val !== null && val !== '') {
          searchParams.append(key, String(val));
        }
      });
      const queryString = searchParams.toString();
      if (queryString) {
        url += (url.includes('?') ? '&' : '?') + queryString;
      }
    }
    return request<T>(url, { method: 'GET' });
  },

  post<T = any>(endpoint: string, body?: any): Promise<ApiResponse<T>> {
    const isFormData = body instanceof FormData;
    return request<T>(endpoint, {
      method: 'POST',
      body: isFormData ? body : JSON.stringify(body || {}),
    });
  },

  put<T = any>(endpoint: string, body?: any): Promise<ApiResponse<T>> {
    const isFormData = body instanceof FormData;
    return request<T>(endpoint, {
      method: 'PUT',
      body: isFormData ? body : JSON.stringify(body || {}),
    });
  },

  delete<T = any>(endpoint: string): Promise<ApiResponse<T>> {
    return request<T>(endpoint, { method: 'DELETE' });
  },
};
