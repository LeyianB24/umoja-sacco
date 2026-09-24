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

    const [vehicles, properties, propertySales] = await Promise.all([
      prisma.vehicles.findMany().catch(() => []),
      prisma.properties.findMany().catch(() => []),
      prisma.propertySales.findMany({ orderBy: { sale_date: 'desc' }, take: 20 }).catch(() => []),
    ]);

    const vehiclesValue = vehicles.reduce((sum, v) => sum + Number(v.purchase_cost || 0), 0);
    const propertiesValue = properties.reduce((sum, p) => sum + Number(p.purchase_cost || 0), 0);

    return apiSuccess({
      vehicles,
      properties,
      property_sales: propertySales,
      portfolio_summary: {
        total_vehicles: vehicles.length,
        total_properties: properties.length,
        vehicles_valuation: vehiclesValue,
        properties_valuation: propertiesValue,
        total_portfolio_value: vehiclesValue + propertiesValue,
      },
    });
  } catch (err: any) {
    return apiError(err.message || 'Failed to fetch investment portfolio', 500);
  }
}
