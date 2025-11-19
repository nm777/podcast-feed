import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { usePage } from '@inertiajs/react';
import AdminLayout from '@/layouts/admin-layout';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';

interface User {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
    approval_status: 'pending' | 'approved' | 'rejected';
    approved_at?: string;
    rejected_at?: string;
    rejection_reason?: string;
    created_at: string;
}

interface PageProps {
    users: User[];
    flash?: {
        success?: string;
        error?: string;
    };
}

export default function UserManagement() {
    const { users, flash } = usePage().props as PageProps;
    const [rejectingUser, setRejectingUser] = useState<User | null>(null);
    const [rejectionReason, setRejectionReason] = useState('');

    const approveForm = useForm({});
    const rejectForm = useForm({ reason: '' });
    const toggleAdminForm = useForm({});

    const handleApprove = (user: User) => {
        approveForm.post(`/admin/users/${user.id}/approve`);
    };

    const handleReject = () => {
        if (!rejectingUser) return;

        rejectForm.post(`/admin/users/${rejectingUser.id}/reject`, {
            onSuccess: () => {
                setRejectingUser(null);
                setRejectionReason('');
            },
        });
    };

    const handleToggleAdmin = (user: User) => {
        toggleAdminForm.post(`/admin/users/${user.id}/toggle-admin`);
    };

    const getStatusBadge = (status: string) => {
        const variants = {
            pending: 'secondary',
            approved: 'default',
            rejected: 'destructive',
        } as const;

        return (
            <Badge variant={variants[status as keyof typeof variants] || 'secondary'}>
                {status.charAt(0).toUpperCase() + status.slice(1)}
            </Badge>
        );
    };

    return (
        <AdminLayout>
            <Head title="User Management" />

            <div className="container mx-auto py-6">
                <div className="mb-6">
                    <h1 className="text-3xl font-bold">User Management</h1>
                    <p className="text-muted-foreground">Manage user registrations and permissions</p>
                </div>

                {flash?.success && (
                    <div className="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                        {flash.success}
                    </div>
                )}

                {flash?.error && (
                    <div className="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                        {flash.error}
                    </div>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>All Users</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b">
                                        <th className="text-left p-2">Name</th>
                                        <th className="text-left p-2">Email</th>
                                        <th className="text-left p-2">Status</th>
                                        <th className="text-left p-2">Admin</th>
                                        <th className="text-left p-2">Joined</th>
                                        <th className="text-left p-2">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {users.map((user) => (
                                        <tr key={user.id} className="border-b">
                                            <td className="p-2">{user.name}</td>
                                            <td className="p-2">{user.email}</td>
                                            <td className="p-2">{getStatusBadge(user.approval_status)}</td>
                                            <td className="p-2">
                                                <Badge variant={user.is_admin ? 'default' : 'secondary'}>
                                                    {user.is_admin ? 'Yes' : 'No'}
                                                </Badge>
                                            </td>
                                            <td className="p-2">{new Date(user.created_at).toLocaleDateString()}</td>
                                            <td className="p-2">
                                                <div className="flex gap-2">
                                                    {user.approval_status === 'pending' && (
                                                        <>
                                                            <Button
                                                                size="sm"
                                                                onClick={() => handleApprove(user)}
                                                                disabled={approveForm.processing}
                                                            >
                                                                {approveForm.processing ? 'Approving...' : 'Approve'}
                                                            </Button>
                                                            <Dialog>
                                                                <DialogTrigger asChild>
                                                                    <Button
                                                                        size="sm"
                                                                        variant="destructive"
                                                                        onClick={() => setRejectingUser(user)}
                                                                    >
                                                                        Reject
                                                                    </Button>
                                                                </DialogTrigger>
                                                                <DialogContent>
                                                                    <DialogHeader>
                                                                        <DialogTitle>Reject User</DialogTitle>
                                                                    </DialogHeader>
                                                                    <div className="space-y-4">
                                                                        <div>
                                                                            <Label htmlFor="reason">Rejection Reason</Label>
                                                                            <Textarea
                                                                                id="reason"
                                                                                value={rejectionReason}
                                                                                onChange={(e) => setRejectionReason(e.target.value)}
                                                                                placeholder="Enter reason for rejection..."
                                                                                className="mt-1"
                                                                            />
                                                                            {rejectForm.errors.reason && (
                                                                                <p className="text-red-500 text-sm mt-1">{rejectForm.errors.reason}</p>
                                                                            )}
                                                                        </div>
                                                                        <div className="flex gap-2 justify-end">
                                                                            <Button
                                                                                variant="outline"
                                                                                onClick={() => {
                                                                                    setRejectingUser(null);
                                                                                    setRejectionReason('');
                                                                                    rejectForm.clearErrors();
                                                                                }}
                                                                            >
                                                                                Cancel
                                                                            </Button>
                                                                            <Button
                                                                                variant="destructive"
                                                                                onClick={handleReject}
                                                                                disabled={!rejectionReason.trim() || rejectForm.processing}
                                                                            >
                                                                                {rejectForm.processing ? 'Rejecting...' : 'Reject'}
                                                                            </Button>
                                                                        </div>
                                                                    </div>
                                                                </DialogContent>
                                                            </Dialog>
                                                        </>
                                                    )}
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => handleToggleAdmin(user)}
                                                        disabled={toggleAdminForm.processing}
                                                    >
                                                        {toggleAdminForm.processing ? 'Updating...' : (user.is_admin ? 'Remove Admin' : 'Make Admin')}
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}