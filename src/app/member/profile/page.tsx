'use client';

import React, { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { useAuth } from '@/context/AuthContext';
import { useToast } from '@/context/ToastContext';
import { formatDate, getInitials } from '@/lib/utils';
import { User, ShieldCheck, Phone, Mail, MapPin, Briefcase, Heart, Save, Camera, Sparkles } from 'lucide-react';
import { ProfilePictureModal } from '@/components/sacco-ui/ProfilePictureModal';

export default function MemberProfilePage() {
  const { user, refreshUser } = useAuth();
  const { toast } = useToast();
  const [profile, setProfile] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [picModalOpen, setPicModalOpen] = useState(false);

  // Edit form state
  const [phone, setPhone] = useState('');
  const [address, setAddress] = useState('');
  const [occupation, setOccupation] = useState('');
  const [nokName, setNokName] = useState('');
  const [nokPhone, setNokPhone] = useState('');
  const [saving, setSaving] = useState(false);

  const fetchProfile = async () => {
    try {
      const res = await api.get('/member/profile');
      if (res.status === 'success') {
        const p = res.data.profile || res.data.member;
        setProfile(p);
        setPhone(p?.phone || '');
        setAddress(p?.address || '');
        setOccupation(p?.occupation || '');
        setNokName(p?.next_of_kin_name || '');
        setNokPhone(p?.next_of_kin_phone || '');
      }
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchProfile();
  }, []);

  const handleUpdate = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    try {
      await api.post('/member/update_profile', {
        phone,
        address,
        occupation,
        next_of_kin_name: nokName,
        next_of_kin_phone: nokPhone,
      });
      toast.success('Profile information updated successfully.');
      await refreshUser();
      fetchProfile();
    } catch (err: any) {
      toast.error(err.message || 'Failed to update profile.');
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return <div style={{ padding: '60px', textAlign: 'center', color: 'var(--text-muted)' }}>Loading Profile...</div>;
  }

  const kycApproved = profile?.kyc_status === 'approved';

  return (
    <>
      <ProfilePictureModal
        isOpen={picModalOpen}
        onClose={() => setPicModalOpen(false)}
        currentImage={profile?.profile_pic_url || user?.profile_pic_url}
        onSuccess={() => {
          fetchProfile();
          refreshUser();
        }}
      />

      <div style={{ display: 'flex', flexDirection: 'column', gap: '28px', maxWidth: '860px' }}>
        <div>
          <h1 style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-main)' }}>Member Profile & Identification</h1>
          <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem' }}>View membership identification records and update contact information</p>
        </div>

        {/* Profile Avatar & Header Card */}
        <div
          className="card"
          style={{
            display: 'flex',
            alignItems: 'center',
            gap: '24px',
            flexWrap: 'wrap',
            padding: '24px 30px',
            background: 'linear-gradient(135deg, rgba(11, 36, 25, 0.05) 0%, rgba(163, 230, 53, 0.06) 100%)',
          }}
        >
          <div style={{ position: 'relative' }}>
            <div
              style={{
                width: '100px',
                height: '100px',
                borderRadius: '50%',
                overflow: 'hidden',
                backgroundColor: 'var(--brand-forest)',
                color: 'var(--brand-lime)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                fontSize: '2rem',
                fontWeight: 800,
                border: '3px solid #FFFFFF',
                boxShadow: '0 4px 14px rgba(0,0,0,0.12)',
              }}
            >
              {profile?.profile_pic_url || user?.profile_pic_url ? (
                <img
                  src={profile?.profile_pic_url || user?.profile_pic_url}
                  alt={profile?.full_name}
                  style={{ width: '100%', height: '100%', objectFit: 'cover' }}
                />
              ) : (
                getInitials(profile?.full_name || user?.name)
              )}
            </div>

            <button
              onClick={() => setPicModalOpen(true)}
              title="Change profile picture"
              style={{
                position: 'absolute',
                bottom: 0,
                right: 0,
                width: '32px',
                height: '32px',
                borderRadius: '50%',
                backgroundColor: 'var(--brand-forest)',
                color: 'var(--brand-lime)',
                border: '2px solid #FFFFFF',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                cursor: 'pointer',
                boxShadow: '0 2px 6px rgba(0,0,0,0.2)',
              }}
            >
              <Camera size={16} />
            </button>
          </div>

          <div style={{ flex: 1, minWidth: '200px' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '10px', flexWrap: 'wrap' }}>
              <h2 style={{ fontSize: '1.4rem', fontWeight: 800, color: 'var(--text-main)', margin: 0 }}>
                {profile?.full_name || user?.name}
              </h2>
              <span className={`badge ${kycApproved ? 'badge-success' : 'badge-warning'}`}>
                {profile?.kyc_status === 'approved' ? 'KYC Verified' : 'KYC Under Review'}
              </span>
            </div>
            <div style={{ fontSize: '0.85rem', color: 'var(--brand-forest)', fontWeight: 700, fontFamily: 'monospace', marginTop: '4px' }}>
              {profile?.member_reg_no || user?.reg_no}
            </div>
            <div style={{ fontSize: '0.8rem', color: 'var(--text-muted)', marginTop: '2px' }}>
              {profile?.occupation || 'Member'} &bull; Joined {formatDate(profile?.join_date || profile?.created_at)}
            </div>
          </div>

          <button onClick={() => setPicModalOpen(true)} className="btn btn-outline-forest" style={{ fontSize: '0.85rem' }}>
            <Camera size={16} /> Update Photo
          </button>
        </div>

        {/* KYC Status Card */}
        <div
          style={{
            padding: '18px 24px',
            borderRadius: '18px',
            backgroundColor: kycApproved ? 'rgba(34, 197, 94, 0.1)' : 'rgba(245, 158, 11, 0.1)',
            border: `1px solid ${kycApproved ? 'rgba(34, 197, 94, 0.25)' : 'rgba(245, 158, 11, 0.25)'}`,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'space-between',
            gap: '16px',
            flexWrap: 'wrap',
          }}
        >
          <div style={{ display: 'flex', alignItems: 'center', gap: '14px' }}>
            <ShieldCheck size={30} color={kycApproved ? '#16a34a' : '#d97706'} />
            <div>
              <div style={{ fontWeight: 800, fontSize: '0.98rem', color: kycApproved ? '#16a34a' : '#d97706' }}>
                Identity Status: {kycApproved ? 'Verified & Approved by SASRA Officer' : 'Verification Under Review'}
              </div>
              <div style={{ fontSize: '0.82rem', color: 'var(--text-muted)' }}>
                {kycApproved ? 'Your official national identity documentation is fully registered.' : 'Our compliance officers will approve your documents within 24 hours.'}
              </div>
            </div>
          </div>
        </div>

        {/* Fixed Identity Details */}
        <div className="card">
          <h3 style={{ fontSize: '1.1rem', fontWeight: 700, marginBottom: '20px', borderBottom: '1px solid var(--border-color)', paddingBottom: '10px' }}>
            Permanent Membership Credentials
          </h3>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: '20px' }}>
            <div>
              <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>Official Full Name</div>
              <div style={{ fontWeight: 700, fontSize: '0.95rem', marginTop: '2px' }}>{profile?.full_name}</div>
            </div>
            <div>
              <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>Member Number</div>
              <div style={{ fontWeight: 800, fontSize: '0.95rem', color: 'var(--brand-forest)', marginTop: '2px' }}>{profile?.member_reg_no}</div>
            </div>
            <div>
              <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>National ID Number</div>
              <div style={{ fontWeight: 700, fontSize: '0.95rem', marginTop: '2px' }}>{profile?.national_id}</div>
            </div>
            <div>
              <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>Email Address</div>
              <div style={{ fontWeight: 700, fontSize: '0.95rem', marginTop: '2px' }}>{profile?.email}</div>
            </div>
            <div>
              <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>Date of Birth</div>
              <div style={{ fontWeight: 700, fontSize: '0.95rem', marginTop: '2px' }}>{formatDate(profile?.dob)}</div>
            </div>
            <div>
              <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>Member Since</div>
              <div style={{ fontWeight: 700, fontSize: '0.95rem', marginTop: '2px' }}>{formatDate(profile?.join_date || profile?.created_at)}</div>
            </div>
          </div>
        </div>

        {/* Editable Contact Info Form */}
        <div className="card">
          <h3 style={{ fontSize: '1.1rem', fontWeight: 700, marginBottom: '20px', borderBottom: '1px solid var(--border-color)', paddingBottom: '10px' }}>
            Editable Contact & Next of Kin Information
          </h3>

          <form onSubmit={handleUpdate} style={{ display: 'flex', flexDirection: 'column', gap: '20px' }}>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: '16px' }}>
              <div>
                <label className="input-label">M-Pesa Mobile Number</label>
                <input
                  type="text"
                  className="input-control"
                  value={phone}
                  onChange={(e) => setPhone(e.target.value)}
                  placeholder="07xx xxx xxx"
                  required
                />
              </div>

              <div>
                <label className="input-label">Operating Route / Location</label>
                <input
                  type="text"
                  className="input-control"
                  value={address}
                  onChange={(e) => setAddress(e.target.value)}
                  placeholder="e.g. Stage 46, Ruiru / Kangemi"
                  required
                />
              </div>

              <div>
                <label className="input-label">Primary Occupation</label>
                <input
                  type="text"
                  className="input-control"
                  value={occupation}
                  onChange={(e) => setOccupation(e.target.value)}
                  placeholder="e.g. Commercial Taxi Driver"
                />
              </div>
            </div>

            <div style={{ borderTop: '1px solid var(--border-color)', paddingTop: '16px', marginTop: '6px' }}>
              <h4 style={{ fontSize: '0.95rem', fontWeight: 700, marginBottom: '14px' }}>Next of Kin (Beneficiary)</h4>
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: '16px' }}>
                <div>
                  <label className="input-label">Next of Kin Full Name</label>
                  <input
                    type="text"
                    className="input-control"
                    value={nokName}
                    onChange={(e) => setNokName(e.target.value)}
                    placeholder="Beneficiary full name"
                    required
                  />
                </div>
                <div>
                  <label className="input-label">Next of Kin Phone</label>
                  <input
                    type="text"
                    className="input-control"
                    value={nokPhone}
                    onChange={(e) => setNokPhone(e.target.value)}
                    placeholder="07xx xxx xxx"
                    required
                  />
                </div>
              </div>
            </div>

            <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: '10px' }}>
              <button type="submit" disabled={saving} className="btn btn-forest">
                <Save size={16} /> {saving ? 'Saving...' : 'Save Profile Changes'}
              </button>
            </div>
          </form>
        </div>
      </div>
    </>
  );
}
