import { NextRequest } from 'next/server';
import { prisma } from '@/lib/prisma';
import { getAuthSession } from '@/lib/auth';
import { apiSuccess, apiError } from '@/lib/api-response';
export const dynamic = 'force-dynamic';
export async function GET(request: NextRequest) {
  try {
    const session = await getAuthSession(request);
    if (!session || session.userType !== 'member') {
      return apiError('Unauthorized', 401);
    }

    const member = await prisma.members.findUnique({
      where: { member_id: session.userId },
      select: {
        member_id: true,
        full_name: true,
        profile_pic_url: true,
        profile_pic_updated_at: true,
      },
    });

    if (!member) {
      return apiError('Member not found', 404);
    }

    return apiSuccess({
      imageUrl: member.profile_pic_url,
      updatedAt: member.profile_pic_updated_at,
    });
  } catch (err: any) {
    return apiError(err.message || 'Failed to fetch profile picture', 500);
  }
}

export async function POST(request: NextRequest) {
  try {
    const session = await getAuthSession(request);
    if (!session || session.userType !== 'member') {
      return apiError('Unauthorized', 401);
    }

    const body = await request.json().catch(() => ({}));
    const imageBase64 = body.image || body.imageBase64 || body.dataUrl;

    if (!imageBase64 || typeof imageBase64 !== 'string') {
      return apiError('Image data (base64 data URL) is required.', 422);
    }

    // Validate data URL format
    if (!/^data:image\/(jpeg|jpg|png|webp);base64,/.test(imageBase64)) {
      return apiError('Invalid format. Only JPG, PNG, and WebP images are allowed.', 400);
    }

    // Size check (approx 5MB base64 is ~7MB string)
    if (imageBase64.length > 7 * 1024 * 1024) {
      return apiError('Image file is too large. Must be under 5MB.', 413);
    }

    const updated = await prisma.members.update({
      where: { member_id: session.userId },
      data: {
        profile_pic_url: imageBase64,
        profile_pic_updated_at: new Date(),
      },
    });

    return apiSuccess(
      {
        success: true,
        imageUrl: updated.profile_pic_url,
        updatedAt: updated.profile_pic_updated_at,
      },
      'Profile picture updated successfully.'
    );
  } catch (err: any) {
    return apiError(err.message || 'Failed to upload profile picture', 500);
  }
}
