export type SelectedService = {
    id: string;
    uuid: string;
    type: 'app' | 'db' | 'service';
    name: string;
    status: string;
    fqdn?: string;
    dbType?: string;
    serverUuid?: string;
    version?: string;
    image?: string;
    description?: string | null;
};
