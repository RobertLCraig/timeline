import { BrowserRouter, Routes, Route } from 'react-router-dom';
import { AuthProvider } from './context/AuthContext';
import Navbar from './components/Navbar';
import ProtectedRoute from './components/ProtectedRoute';
import Landing from './pages/Landing';
import Login from './pages/Login';
import Register from './pages/Register';
import Dashboard from './pages/Dashboard';
import CreateGroup from './pages/CreateGroup';
import GroupTimeline from './pages/GroupTimeline';
import GroupSettings from './pages/GroupSettings';
import EventForm from './pages/EventForm';
import AdminPanel from './pages/AdminPanel';
import Profile from './pages/Profile';
import './index.css';

export default function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <Navbar />
        <Routes>
          {/* Public */}
          <Route path="/" element={<Landing />} />
          <Route path="/login" element={<Login />} />
          <Route path="/register" element={<Register />} />
          <Route path="/g/:slug" element={<GroupTimeline />} />

          {/* Protected */}
          <Route path="/dashboard" element={<ProtectedRoute><Dashboard /></ProtectedRoute>} />
          <Route path="/profile" element={<ProtectedRoute><Profile /></ProtectedRoute>} />
          <Route path="/groups/new" element={<ProtectedRoute><CreateGroup /></ProtectedRoute>} />
          <Route path="/g/:slug/settings" element={<ProtectedRoute><GroupSettings /></ProtectedRoute>} />
          <Route path="/g/:slug/events/new" element={<ProtectedRoute><EventForm /></ProtectedRoute>} />
          <Route path="/g/:slug/events/:id/edit" element={<ProtectedRoute><EventForm /></ProtectedRoute>} />
          <Route path="/admin" element={<ProtectedRoute adminOnly><AdminPanel /></ProtectedRoute>} />
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  );
}
