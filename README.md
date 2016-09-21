# Mysqli-Model
Mysqli Query Builder for my Codeigniter query builder, inject a mysqli instance to use it.


# Usage for Codeigniter

````
        $this->load->library('models');

        $this->models->from('f_test_sql')->insert(['title' => '可靠']);

        echo $this->models->getLastError();
        var_dump($this->models->getLastSql());

        $result = $this->models
            ->from('f_test_sql')
            ->where()->select();
        var_dump($result->fetch_all());

        $result = $this->models
            ->from('f_test_sql')
            ->where(['id' => [2]])->update(['title' => '123445']);
        var_dump($result);

        var_dump($this->models->from('f_test_sql')->where(['$or' => [
            '1=1', '2=2'
        ]])->getAllWithKey('id'));

        var_dump($this->models->getLastSql());

        var_dump($this->models->from('f_test_sql')->where(['$or' => [
            '1=1', '2=2'
        ]])->getAll());

        var_dump($this->models->from('f_test_sql')->where(['$or' => [
            '1=1', '2=2'
        ]])->getScala());
        $this->models->from('f_test_sql')->where(['title' => '可靠'])->delete();


