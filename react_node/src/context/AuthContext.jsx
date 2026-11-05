import React, { createContext, useContext, useState, useEffect } from 'react';

const AuthContext = createContext(null);

export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(() => {
    try {
      const saved = localStorage.getItem('dosewise_user');
      return saved ? JSON.parse(saved) : null;
    } catch {
      return null;
    }
  });

  const [token, setToken] = useState(() => {
    return localStorage.getItem('dosewise_token') || null;
  });

  const [loading, setLoading] = useState(false);

  // Validate session on mount
  useEffect(() => {
    if (token) {
      fetch('/api/auth/me', {
        headers: { Authorization: `Bearer ${token}` }
      })
        .then(res => res.json())
        .then(data => {
          if (!data || !data.user) {
            setUser(null);
            setToken(null);
            localStorage.removeItem('dosewise_user');
            localStorage.removeItem('dosewise_token');
          } else {
            setUser(data.user);
            localStorage.setItem('dosewise_user', JSON.stringify(data.user));
          }
        })
        .catch(() => {
          // If network is offline, keep cached user profile
        });
    }
  }, [token]);

  const login = async (email, password, demo_user = null) => {
    setLoading(true);
    try {
      const res = await fetch('/api/auth/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password, demo_user })
      });
      const data = await res.json();
      if (!res.ok) {
        throw new Error(data.error || 'Login failed');
      }
      setUser(data.user);
      setToken(data.token);
      localStorage.setItem('dosewise_user', JSON.stringify(data.user));
      localStorage.setItem('dosewise_token', data.token);
      return data.user;
    } finally {
      setLoading(false);
    }
  };

  const register = async (formData) => {
    setLoading(true);
    try {
      const res = await fetch('/api/auth/register', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(formData)
      });
      const data = await res.json();
      if (!res.ok) {
        throw new Error(data.error || 'Registration failed');
      }
      setUser(data.user);
      setToken(data.token);
      localStorage.setItem('dosewise_user', JSON.stringify(data.user));
      localStorage.setItem('dosewise_token', data.token);
      return data.user;
    } finally {
      setLoading(false);
    }
  };

  const logout = async () => {
    try {
      if (token) {
        await fetch('/api/auth/logout', {
          method: 'POST',
          headers: { Authorization: `Bearer ${token}` }
        });
      }
    } catch {
      // Ignore network errors on logout
    }
    setUser(null);
    setToken(null);
    localStorage.removeItem('dosewise_user');
    localStorage.removeItem('dosewise_token');
  };

  return (
    <AuthContext.Provider value={{
      user,
      token,
      loading,
      login,
      register,
      logout,
      isAdmin: user?.role === 'admin',
      isCitizen: !!user && user.role !== 'admin'
    }}>
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = () => useContext(AuthContext);
