export type SelectedService = {
    id: string;
    uuid: string;
    type: 'app' | 'db';
    name: string;
    status: string;
    fqdn?: string;
    dbType?: string;
    serverUuid?: string;
};
