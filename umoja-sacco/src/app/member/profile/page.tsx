'use client';

import React, { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { useAuth } from '@/context/AuthContext';
import { useToast } from '@/context/ToastContext';
import { formatDate } from '@/lib/utils';
import { User, ShieldCheck, Phone, Mail, MapPin, Briefcase, Heart, Save } from 'lucide-react';

export default function MemberProfilePage() {
  const { user, refreshUser } = useAuth();
  const { toast } = useToast();
  const [profile, setProfile] = useState<any>(null);
  const [loading, setLoading] = useState(true);

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
        const p = res.data.profile;
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
    <div style={{ display: 'flex', flexDirection: 'column', gap: '28px', maxWidth: '860px' }}>
      <div>
        <h1 style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-main)' }}>Member Profile</h1>
        <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem' }}>View membership identification records and update contact info</p>
      </div>

      {/* KYC Status Card */}
      <div
        style={{
          padding: '20px 24px',
          borderRadius: '18px',
          backgroundColor: kycApproved ? 'rgba(34, 197, 94, 0.1)' : 'rgba(245, 158, 11, 0.1)',
          border: `1px solid ${kycApproved ? 'rgba(34, 197, 94, 0.25)' : 'rgba(245, 158, 11, 0.25)'}`,
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
        }}
      >
        <div style={{ display: 'flex', alignItems: 'center', gap: '14px' }}>
          <ShieldCheck size={32} color={kycApproved ? '#16a34a' : '#d97706'} />
          <div>
            <div style={{ fontWeight: 800, fontSize: '1rem', color: kycApproved ? '#16a34a' : '#d97706' }}>
              KYC Status: {kycApproved ? 'Verified & Approved' : 'Verification Under Review'}
            </div>
            <div style={{ fontSize: '0.82rem', color: 'var(--text-muted)' }}>
              {kycApproved ? 'Your national identity documentation has been validated.' : 'Our compliance officers are verifying your identification.'}
            </div>
          </div>
        </div>
        <span className={`badge ${kycApproved ? 'badge-success' : 'badge-warning'}`}>
          {profile?.kyc_status || 'Pending'}
        </span>
      </div>

      {/* Fixed Identity Details */}
      <div className="card">
        <h3 style={{ fontSize: '1.1rem', fontWeight: 700, marginBottom: '20px', borderBottom: '1px solid var(--border-color)', paddingBottom: '10px' }}>
          Permanent Identity Credentials
        </h3>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: '20px' }}>
          <div>
            <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>Official Full Name</div>
            <div style={{ fontWeight: 700, fontSize: '1rem', marginTop: '2px' }}>{profile?.full_name}</div>
          </div>
          <div>
            <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>Member Reg Number</div>
            <div style={{ fontWeight: 800, fontSize: '1rem', color: 'var(--brand-forest)', marginTop: '2px' }}>{profile?.member_reg_no}</div>
          </div>
          <div>
            <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>National ID Number</div>
            <div style={{ fontWeight: 700, fontSize: '1rem', marginTop: '2px' }}>{profile?.national_id}</div>
          </div>
          <div>
            <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>Email Address</div>
            <div style={{ fontWeight: 700, fontSize: '1rem', marginTop: '2px' }}>{profile?.email}</div>
          </div>
          <div>
            <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>Date of Birth</div>
            <div style={{ fontWeight: 700, fontSize: '1rem', marginTop: '2px' }}>{formatDate(profile?.dob)}</div>
          </div>
          <div>
            <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>Member Since</div>
            <div style={{ fontWeight: 700, fontSize: '1rem', marginTop: '2px' }}>{formatDate(profile?.created_at)}</div>
          </div>
        </div>
      </div>

      {/* Editable Contact Info */}
      <div className="card">
        <h3 style={{ fontSize: '1.1rem', fontWeight: 700, marginBottom: '20px', borderBottom: '1px solid var(--border-color)', paddingBottom: '10px' }}>
          Update Contact & Next of Kin Information
        </h3>
        <form onSubmit={handleUpdate} style={{ display: 'flex', flexDirection: 'column', gap: '18px' }}>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: '18px' }}>
            <div>
              <label className="input-label">M-Pesa Phone Number</label>
              <input
                type="text"
                className="input-control"
                value={phone}
                onChange={(e) => setPhone(e.target.value)}
                required
              />
            </div>
            <div>
              <label className="input-label">Physical Address / County</label>
              <input
                type="text"
                className="input-control"
                value={address}
                onChange={(e) => setAddress(e.target.value)}
              />
            </div>
            <div>
              <label className="input-label">Occupation / Transport Route</label>
              <input
                type="text"
                className="input-control"
                value={occupation}
                onChange={(e) => setOccupation(e.target.value)}
              />
            </div>
            <div>
              <label className="input-label">Next of Kin Full Name</label>
              <input
                type="text"
                className="input-control"
                value={nokName}
                onChange={(e) => setNokName(e.target.value)}
                required
              />
            </div>
            <div>
              <label className="input-label">Next of Kin Phone Number</label>
              <input
                type="text"
                className="input-control"
                value={nokPhone}
                onChange={(e) => setNokPhone(e.target.value)}
                required
              />
            </div>
          </div>

          <div>
            <button type="submit" disabled={saving} className="btn btn-lime" style={{ marginTop: '8px' }}>
              <Save size={16} /> {saving ? 'Saving...' : 'Save Profile Changes'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
