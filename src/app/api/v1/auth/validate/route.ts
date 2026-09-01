import { NextRequest } from 'next/server';
import { prisma } from '@/lib/prisma';
import { apiSuccess, apiError } from '@/lib/api-response';

export async function POST(request: NextRequest) {
  try {
    const body = await request.json().catch(() => ({}));
    const { field, value } = body;

    if (!field || typeof value !== 'string') {
      return apiError('Field name and value are required for validation.', 422);
    }

    const trimmed = value.trim();

    if (field === 'email') {
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(trimmed)) {
        return apiSuccess({ available: false, error: 'Please enter a valid email address.' });
      }
      const existing = await prisma.members.findFirst({
        where: { email: trimmed },
        select: { member_id: true },
      });
      return apiSuccess({
        available: !existing,
        error: existing ? 'This email is already registered.' : null,
      });
    }

    if (field === 'phone') {
      let normalized = trimmed.replace(/[^\d+]/g, '');
      if (!normalized.startsWith('+')) {
        if (/^0(\d{8,9})$/.test(normalized)) {
          normalized = '+254' + normalized.substring(1);
        } else if (/^7(\d{8})$/.test(normalized) || /^1(\d{8})$/.test(normalized)) {
          normalized = '+254' + normalized;
        }
      }
      if (!/^\+254\d{9}$/.test(normalized) && !/^0[17]\d{8}$/.test(trimmed)) {
        return apiSuccess({
          available: false,
          error: 'Phone must be valid Kenyan format (e.g. 0712 345 678 or +254712345678).',
        });
      }
      const existing = await prisma.members.findFirst({
        where: {
          OR: [{ phone: normalized }, { phone: trimmed }],
        },
        select: { member_id: true },
      });
      return apiSuccess({
        available: !existing,
        normalized,
        error: existing ? 'Phone number is already in use by another member.' : null,
      });
    }

    if (field === 'national_id') {
      if (!/^\d{7,8}$/.test(trimmed)) {
        return apiSuccess({
          available: false,
          error: 'National ID must be 7 or 8 digits.',
        });
      }
      const existing = await prisma.members.findFirst({
        where: { national_id: trimmed },
        select: { member_id: true },
      });
      return apiSuccess({
        available: !existing,
        error: existing ? 'National ID is already registered.' : null,
      });
    }

    return apiSuccess({ available: true });
  } catch (err: any) {
    return apiError(err.message || 'Validation error', 500);
  }
}
