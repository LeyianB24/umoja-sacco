'use client';

import React, { useState, useRef } from 'react';
import { api } from '@/lib/api';
import { useToast } from '@/context/ToastContext';
import { useAuth } from '@/context/AuthContext';
import { Upload, X, Camera, Check, AlertCircle, RefreshCw } from 'lucide-react';
import { getInitials } from '@/lib/utils';

interface ProfilePictureModalProps {
  isOpen: boolean;
  onClose: () => void;
  currentImage?: string | null;
  onSuccess?: (url: string) => void;
}

export function ProfilePictureModal({ isOpen, onClose, currentImage, onSuccess }: ProfilePictureModalProps) {
  const { user, refreshUser } = useAuth();
  const { toast } = useToast();
  const fileInputRef = useRef<HTMLInputElement>(null);

  const [previewUrl, setPreviewUrl] = useState<string | null>(null);
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  if (!isOpen) return null;

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setError(null);
    const file = e.target.files?.[0];
    if (!file) return;

    // Validate format
    const validFormats = ['image/jpeg', 'image/png', 'image/webp'];
    if (!validFormats.includes(file.type)) {
      setError('Please upload a JPG, PNG, or WebP image.');
      return;
    }

    // Validate max size (5MB)
    if (file.size > 5 * 1024 * 1024) {
      setError('Image must be under 5MB.');
      return;
    }

    // Dimension check
    const img = new Image();
    const objectUrl = URL.createObjectURL(file);
    img.onload = () => {
      URL.revokeObjectURL(objectUrl);
      if (img.width < 200 || img.height < 200) {
        setError('Image must be at least 200x200px.');
        return;
      }
      if (img.width > 4000 || img.height > 4000) {
        setError('Image must be under 4000x4000px.');
        return;
      }

      // Convert to square cropped data URL canvas
      const canvas = document.createElement('canvas');
      const size = Math.min(img.width, img.height);
      canvas.width = 400;
      canvas.height = 400;
      const ctx = canvas.getContext('2d');
      if (ctx) {
        const startX = (img.width - size) / 2;
        const startY = (img.height - size) / 2;
        ctx.drawImage(img, startX, startY, size, size, 0, 0, 400, 400);
        const croppedDataUrl = canvas.toDataURL('image/jpeg', 0.85);
        setPreviewUrl(croppedDataUrl);
        setSelectedFile(file);
      }
    };
    img.src = objectUrl;
  };

  const handleUpload = async () => {
    if (!previewUrl) return;

    setLoading(true);
    setError(null);

    try {
      const res = await api.post('/member/profile_picture', {
        image: previewUrl,
      });

      toast.success('Profile picture updated successfully!');
      if (onSuccess) onSuccess(res.data.imageUrl || previewUrl);
      await refreshUser();
      onClose();
    } catch (err: any) {
      setError(err.message || 'Upload failed. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div
      style={{
        position: 'fixed',
        inset: 0,
        backgroundColor: 'rgba(0, 0, 0, 0.65)',
        backdropFilter: 'blur(4px)',
        zIndex: 1100,
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        padding: '16px',
      }}
    >
      <div
        className="card"
        style={{
          width: '100%',
          maxWidth: '420px',
          padding: '28px',
          borderRadius: '24px',
          backgroundColor: 'var(--bg-surface)',
          boxShadow: '0 25px 50px rgba(0,0,0,0.3)',
          position: 'relative',
        }}
      >
        {/* Header */}
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
          <div>
            <h3 style={{ fontSize: '1.2rem', fontWeight: 800, color: 'var(--text-main)', margin: 0 }}>
              Upload Profile Picture
            </h3>
            <p style={{ fontSize: '0.8rem', color: 'var(--text-muted)', margin: '2px 0 0' }}>
              Used for membership pass & KYC verification
            </p>
          </div>
          <button
            onClick={onClose}
            style={{
              background: 'none',
              border: 'none',
              cursor: 'pointer',
              color: 'var(--text-muted)',
              padding: '4px',
            }}
          >
            <X size={20} />
          </button>
        </div>

        {error && (
          <div
            style={{
              display: 'flex',
              alignItems: 'center',
              gap: '8px',
              padding: '10px 14px',
              borderRadius: '12px',
              backgroundColor: 'rgba(239, 68, 68, 0.1)',
              color: '#dc2626',
              fontSize: '0.82rem',
              fontWeight: 600,
              marginBottom: '16px',
            }}
          >
            <AlertCircle size={16} />
            <span>{error}</span>
          </div>
        )}

        {/* Picture Preview Box */}
        <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '16px', margin: '16px 0 24px' }}>
          <div
            style={{
              width: '140px',
              height: '140px',
              borderRadius: '50%',
              overflow: 'hidden',
              border: '3px solid var(--brand-forest)',
              boxShadow: '0 4px 14px rgba(11, 36, 25, 0.15)',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              backgroundColor: '#0F392B',
              color: 'var(--brand-lime)',
              fontSize: '2.5rem',
              fontWeight: 800,
              position: 'relative',
            }}
          >
            {previewUrl || currentImage ? (
              <img
                src={previewUrl || currentImage || ''}
                alt="Profile Preview"
                style={{ width: '100%', height: '100%', objectFit: 'cover' }}
              />
            ) : (
              <span>{getInitials(user?.name)}</span>
            )}
          </div>

          <input
            type="file"
            ref={fileInputRef}
            onChange={handleFileChange}
            accept="image/jpeg,image/png,image/webp"
            style={{ display: 'none' }}
          />

          <button
            type="button"
            onClick={() => fileInputRef.current?.click()}
            className="btn btn-outline-forest"
            style={{ fontSize: '0.82rem', padding: '8px 16px' }}
          >
            <Camera size={16} /> Choose Image File
          </button>
          <span style={{ fontSize: '0.72rem', color: 'var(--text-muted)' }}>
            JPG, PNG, or WebP &bull; Max 5MB &bull; 200x200 min
          </span>
        </div>

        {/* Action Buttons */}
        <div style={{ display: 'flex', gap: '10px' }}>
          <button
            type="button"
            onClick={onClose}
            className="btn btn-outline-forest"
            style={{ flex: 1 }}
          >
            Cancel
          </button>
          <button
            type="button"
            disabled={!previewUrl || loading}
            onClick={handleUpload}
            className="btn btn-forest"
            style={{ flex: 1 }}
          >
            {loading ? 'Uploading...' : 'Save Picture'}
          </button>
        </div>
      </div>
    </div>
  );
}
