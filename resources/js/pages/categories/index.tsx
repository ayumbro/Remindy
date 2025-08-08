import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { AlertCircle, Edit, Eye, Folder, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/utils';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Categories',
        href: '/categories',
    },
];

interface Category {
    id: number;
    name: string;
    color?: string;
    description?: string;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    subscriptions_count: number;
    display_color: string;
}

interface CategoriesIndexProps {
    categories: Category[];
}

export default function CategoriesIndex({ categories = [] }: CategoriesIndexProps) {
    const { errors } = usePage().props as any;
    const [categoryToDelete, setCategoryToDelete] = useState<Category | null>(null);
    const [deletionError, setDeletionError] = useState<string | null>(null);

    const handleDelete = (category: Category) => {
        // Clear any previous error
        setDeletionError(null);

        router.delete(route('categories.destroy', category.id), {
            onSuccess: () => {
                setCategoryToDelete(null);
                setDeletionError(null);
            },
            onError: (errors) => {
                // Handle validation errors from the backend
                if (errors.category) {
                    setDeletionError(errors.category);
                } else {
                    setDeletionError('An error occurred while trying to delete the category.');
                }
            },
        });
    };

    const closeDialog = () => {
        setCategoryToDelete(null);
        setDeletionError(null);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Categories" />

            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold">Categories</h1>
                        <p className="text-muted-foreground">Organize your subscriptions with custom categories</p>
                    </div>
                    <Link href={route('categories.create')}>
                        <Button>
                            <Plus className="mr-2 h-4 w-4" />
                            Add Category
                        </Button>
                    </Link>
                </div>

                {categories.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <Folder className="text-muted-foreground mb-4 h-12 w-12" />
                            <h3 className="mb-2 text-lg font-semibold">No categories yet</h3>
                            <p className="text-muted-foreground mb-4 text-center">Create your first category to organize your subscriptions</p>
                            <Link href={route('categories.create')}>
                                <Button>
                                    <Plus className="mr-2 h-4 w-4" />
                                    Add Category
                                </Button>
                            </Link>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {categories.map((category) => (
                            <Card key={category.id} className={`${!category.is_active ? 'opacity-60' : ''}`}>
                                <CardHeader className="pb-3">
                                    <div className="flex items-start justify-between">
                                        <div className="flex items-center gap-3">
                                            <div className="h-4 w-4 flex-shrink-0 rounded-full" style={{ backgroundColor: category.display_color }} />
                                            <div>
                                                <CardTitle className="text-base">{category.name}</CardTitle>
                                                {category.description && (
                                                    <CardDescription className="text-sm">{category.description}</CardDescription>
                                                )}
                                            </div>
                                        </div>
                                        <div className="flex gap-1">{!category.is_active && <Badge variant="secondary">Inactive</Badge>}</div>
                                    </div>
                                </CardHeader>
                                <CardContent className="pt-0">
                                    <div className="flex items-center justify-between">
                                        <div className="flex gap-2">
                                            <Link href={route('categories.show', category.id)}>
                                                <Button size="sm" variant="outline">
                                                    <Eye className="h-3 w-3" />
                                                </Button>
                                            </Link>
                                            <Link href={route('categories.edit', category.id)}>
                                                <Button size="sm" variant="outline">
                                                    <Edit className="h-3 w-3" />
                                                </Button>
                                            </Link>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                className="text-red-600 hover:text-red-700"
                                                onClick={() => setCategoryToDelete(category)}
                                            >
                                                <Trash2 className="h-3 w-3" />
                                            </Button>
                                        </div>
                                        <div className="text-right">
                                            <span className="text-sm font-medium">{category.subscriptions_count}</span>
                                            <p className="text-muted-foreground text-xs">
                                                subscription{category.subscriptions_count !== 1 ? 's' : ''}
                                            </p>
                                        </div>
                                    </div>

                                    <div className="mt-3 border-t pt-3">
                                        <p className="text-muted-foreground text-xs">Created: {formatDate(category.created_at)}</p>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}

                {/* Delete Confirmation Dialog */}
                <Dialog open={!!categoryToDelete} onOpenChange={closeDialog}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Delete Category</DialogTitle>
                            <DialogDescription>
                                Are you sure you want to delete "{categoryToDelete?.name}"? This action cannot be undone.
                                {categoryToDelete?.subscriptions_count > 0 && (
                                    <span className="mt-2 block text-amber-600">
                                        Warning: This category is currently used by {categoryToDelete.subscriptions_count} subscription(s).
                                    </span>
                                )}
                            </DialogDescription>
                        </DialogHeader>

                        {/* Error Alert */}
                        {deletionError && (
                            <Alert variant="destructive">
                                <AlertCircle className="h-4 w-4" />
                                <AlertDescription>
                                    {deletionError}
                                    {categoryToDelete?.subscriptions_count > 0 && (
                                        <span className="mt-2 block">
                                            To delete this category, please first remove or reassign all subscriptions that use it.
                                        </span>
                                    )}
                                </AlertDescription>
                            </Alert>
                        )}

                        <DialogFooter>
                            <Button variant="outline" onClick={closeDialog}>
                                Cancel
                            </Button>
                            <Button
                                variant="destructive"
                                onClick={() => categoryToDelete && handleDelete(categoryToDelete)}
                                disabled={categoryToDelete?.subscriptions_count > 0}
                                title={categoryToDelete?.subscriptions_count > 0 ? 'Cannot delete category with active subscriptions' : ''}
                            >
                                {categoryToDelete?.subscriptions_count > 0 ? 'Cannot Delete' : 'Delete Category'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
