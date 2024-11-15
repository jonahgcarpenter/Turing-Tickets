# Turing-Tickets

update this php to set option All to only open tickets 

    // Apply search filter
    if ($search !== '') {
        $baseQueryOpen .= " AND id = :ticket_id";
        $baseQueryClosed .= " AND id = :ticket_id";
        $params[':ticket_id'] = $search;
    }