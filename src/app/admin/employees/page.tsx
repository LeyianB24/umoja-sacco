'use client';

import React, { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { formatKES, formatDate } from '@/lib/utils';
import { Briefcase, UserPlus, Users, Printer } from 'lucide-react';

export default function AdminEmployeesPage() {
  const [employees, setEmployees] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchEmployees = async () => {
      try {
        const res = await api.get('/admin/employees');
        if (res.status === 'success') {
          setEmployees(res.data.employees || []);
        }
      } catch (err) {
        console.error(err);
      } finally {
        setLoading(false);
      }
    };
    fetchEmployees();
  }, []);

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '28px' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '16px' }}>
        <div>
          <h1 style={{ fontSize: '1.75rem', fontWeight: 800, color: 'var(--text-main)', margin: 0 }}>Employee Directory</h1>
          <p style={{ color: 'var(--text-muted)', fontSize: '0.88rem', margin: '4px 0 0' }}>Manage Sacco staff, statutory numbers (KRA, NSSF, NHIF), and job titles</p>
        </div>

        <button
          onClick={() => window.print()}
          className="btn btn-outline-forest"
          style={{ borderRadius: '50px', padding: '10px 20px' }}
        >
          <Printer size={16} /> Print Staff Directory
        </button>
      </div>

      <div className="card" style={{ padding: 0, overflow: 'hidden' }}>
        <div className="table-container" style={{ border: 'none', borderRadius: 0 }}>
          <table className="data-table">
            <thead>
              <tr>
                <th>Emp No</th>
                <th>Full Name</th>
                <th>Job Title</th>
                <th>Phone</th>
                <th>Company Email</th>
                <th>Basic Salary</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              {employees.length ? (
                employees.map((e, i) => (
                  <tr key={i}>
                    <td style={{ fontWeight: 800, fontFamily: 'monospace' }}>{e.employee_no}</td>
                    <td style={{ fontWeight: 600 }}>{e.full_name}</td>
                    <td>{e.job_title || 'Officer'}</td>
                    <td>{e.phone}</td>
                    <td>{e.company_email || e.personal_email}</td>
                    <td style={{ fontWeight: 700 }}>{formatKES(e.salary)}</td>
                    <td><span className="badge badge-success">{e.status || 'Active'}</span></td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={7} style={{ textAlign: 'center', padding: '36px', color: 'var(--text-muted)' }}>
                    {loading ? 'Loading employee records...' : 'No employee records found.'}
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
