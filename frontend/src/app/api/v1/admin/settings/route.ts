import { NextRequest } from 'next/server';
import { prisma } from '@/lib/prisma';
import { getAuthSession } from '@/lib/auth';
import { apiSuccess, apiError } from '@/lib/api-response';

export async function GET(request: NextRequest) {
  try {
    const session = await getAuthSession(request);
    if (!session || session.userType !== 'admin') {
      return apiError('Unauthorized', 401);
    }

    const settings = await prisma.systemSettings.findMany().catch(() => []);
    const settingsMap = Object.fromEntries(settings.map((s) => [s.setting_key, s.setting_value]));

    return apiSuccess({
      settings: {
        sacco_name: settingsMap.sacco_name || 'Umoja Drivers SACCO Society Ltd',
        registration_fee: settingsMap.registration_fee || '1000',
        min_monthly_savings: settingsMap.min_monthly_savings || '1000',
        share_value: settingsMap.share_value || '20',
        loan_interest_rate: settingsMap.loan_interest_rate || '12',
        late_fine_rate: settingsMap.late_fine_rate || '0.05',
        currency: settingsMap.currency || 'KES',
        paybill_number: settingsMap.paybill_number || '247247',
        support_email: settingsMap.support_email || 'info@umojasacco.co.ke',
        ...settingsMap,
      },
    });
  } catch (err: any) {
    return apiError(err.message || 'Failed to fetch settings', 500);
  }
}

export async function POST(request: NextRequest) {
  try {
    const session = await getAuthSession(request);
    if (!session || session.userType !== 'admin') {
      return apiError('Unauthorized', 401);
    }

    const body = await request.json().catch(() => ({}));
    const settings = body.settings || body;

    for (const [key, val] of Object.entries(settings)) {
      if (typeof val === 'string' || typeof val === 'number') {
        await prisma.systemSettings.upsert({
          where: { setting_key: key },
          update: { setting_value: String(val) },
          create: { setting_key: key, setting_value: String(val) },
        }).catch(() => null);
      }
    }

    return apiSuccess(null, 'System settings updated successfully.');
  } catch (err: any) {
    return apiError(err.message || 'Failed to update system settings', 500);
  }
}
